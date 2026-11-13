<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

use InvalidArgumentException;

/**
 * Fixed-window limits per client address and for the whole instance.
 * Counters live in the database ({@see DatabaseCounterStore}) and go up
 * atomically, so concurrent requests are all counted.
 *
 * The counters hold no client addresses: an address is replaced by a keyed
 * HMAC with the installation's secret, shortened to 128 bits. Without the
 * secret the value cannot be traced back to an address, not even by trying
 * every IPv4 address.
 */
final class RateLimiter
{
    private const PREFIX = 'app-connect/';

    public function __construct(
        private readonly CounterStore $store,
        private readonly Limits $limits,
        private readonly string $secret,
        private readonly ?\Closure $clock = null
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('The rate limiter needs a secret');
        }
    }

    /** Whether requests from $address are currently refused. */
    public function isBlocked(string $address): bool
    {
        return $this->store->read($this->addressKey($address)) >= $this->limits->perAddress
            || $this->store->read($this->globalKey()) >= $this->limits->global;
    }

    /** Counts one event from $address; each counter goes up in one atomic step. */
    public function record(string $address): void
    {
        $this->store->increment($this->addressKey($address), $this->limits->perAddressWindow);
        $this->store->increment($this->globalKey(), $this->limits->globalWindow);
    }

    /** The cache key of $address in the current window. */
    public function addressKey(string $address): string
    {
        $window = intdiv($this->now(), $this->limits->perAddressWindow);
        $digest = substr(hash_hmac('sha256', $address, $this->secret), 0, 32);
        return self::PREFIX . $this->limits->name . '/' . $digest . '/' . $window;
    }

    private function globalKey(): string
    {
        return self::PREFIX . $this->limits->name . '/all/' . intdiv($this->now(), $this->limits->globalWindow);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
