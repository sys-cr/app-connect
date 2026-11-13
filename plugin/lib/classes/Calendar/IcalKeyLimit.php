<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Calendar;

use AppConnect\RateLimit\CounterStore;

/**
 * How many calendar keys one person may have created from the app: at most
 * {@see MAX_CREATIONS} in a fixed window of {@see WINDOW} seconds. Reset and
 * create is the only way to a new key, so this bounds how many keys a
 * person – or a stolen app token – can churn through. Counters live in the
 * cache, so a flushed cache only resets them early.
 */
final class IcalKeyLimit
{
    public const MAX_CREATIONS = 5;
    public const WINDOW = 3600;

    private const PREFIX = 'app-connect/ical-key-creations/';

    public function __construct(private readonly CounterStore $store, private readonly ?\Closure $clock = null)
    {
    }

    /** Whether $userId may have another key created now. */
    public function allows(string $userId): bool
    {
        return $this->store->read($this->key($userId)) < self::MAX_CREATIONS;
    }

    /** Counts one created key. */
    public function record(string $userId): void
    {
        $this->store->increment($this->key($userId), self::WINDOW);
    }

    /** Seconds until the current window ends. */
    public function retryAfter(): int
    {
        return self::WINDOW - $this->now() % self::WINDOW;
    }

    private function key(string $userId): string
    {
        return self::PREFIX . $userId . '/' . intdiv($this->now(), self::WINDOW);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
