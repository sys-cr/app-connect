<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/** Short-lived counters behind {@see RateLimiter}. */
interface CounterStore
{
    public function read(string $key): int;

    public function write(string $key, int $value, int $ttlSeconds): void;
}
