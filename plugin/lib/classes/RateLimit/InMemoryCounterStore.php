<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/** Counters in memory, for tests. */
final class InMemoryCounterStore implements CounterStore
{
    /** @var array<string, int> */
    private array $values = [];

    public function read(string $key): int
    {
        return $this->values[$key] ?? 0;
    }

    public function write(string $key, int $value, int $ttlSeconds): void
    {
        $this->values[$key] = $value;
    }
}
