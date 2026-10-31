<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/**
 * Counters in the Stud.IP cache (\Studip\Cache\Factory from 6.3 on,
 * StudipCacheFactory before).
 */
final class StudipCacheCounterStore implements CounterStore
{
    public function read(string $key): int
    {
        $value = $this->cache()->read($key);
        return is_numeric($value) ? (int) $value : 0;
    }

    public function write(string $key, int $value, int $ttlSeconds): void
    {
        $this->cache()->write($key, (string) $value, $ttlSeconds);
    }

    private function cache(): object
    {
        if (class_exists(\Studip\Cache\Factory::class)) {
            return \Studip\Cache\Factory::getCache();
        }
        return \StudipCacheFactory::getCache();
    }
}
