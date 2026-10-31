<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/**
 * Fixed-window limits for failed pairing attempts: per client address and for
 * the whole instance. Counters live in the cache, so a flushed cache only
 * resets them early.
 */
final class RateLimiter
{
    public const PER_ADDRESS = 10;
    public const PER_ADDRESS_WINDOW = 600;
    public const GLOBAL = 100;
    public const GLOBAL_WINDOW = 60;

    private const PREFIX = 'app-connect/pairing-failures/';

    public function __construct(private readonly CounterStore $store, private readonly ?\Closure $clock = null)
    {
    }

    /** Whether redemptions from $address are currently refused. */
    public function isBlocked(string $address): bool
    {
        return $this->store->read($this->addressKey($address)) >= self::PER_ADDRESS
            || $this->store->read($this->globalKey()) >= self::GLOBAL;
    }

    /** Counts one failed redemption. */
    public function recordFailure(string $address): void
    {
        $addressKey = $this->addressKey($address);
        $this->store->write($addressKey, $this->store->read($addressKey) + 1, self::PER_ADDRESS_WINDOW);
        $globalKey = $this->globalKey();
        $this->store->write($globalKey, $this->store->read($globalKey) + 1, self::GLOBAL_WINDOW);
    }

    private function addressKey(string $address): string
    {
        // The address is hashed so the cache holds no IP addresses.
        $window = intdiv($this->now(), self::PER_ADDRESS_WINDOW);
        return self::PREFIX . hash('sha256', $address) . '/' . $window;
    }

    private function globalKey(): string
    {
        return self::PREFIX . 'all/' . intdiv($this->now(), self::GLOBAL_WINDOW);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
