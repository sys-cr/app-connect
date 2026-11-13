<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/** Short-lived counters behind {@see RateLimiter} and {@see \AppConnect\Calendar\IcalKeyLimit}. */
interface CounterStore
{
    /** The current count of $key, 0 when it does not exist or has expired. */
    public function read(string $key): int;

    /**
     * Adds one to $key in a single atomic step and returns the new count, so
     * concurrent requests never overwrite each other. A new key expires
     * after $ttlSeconds.
     */
    public function increment(string $key, int $ttlSeconds): int;
}
