<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/**
 * Counters in the table `app_connect_counters`. One statement adds one, so
 * concurrent requests cannot lose a count, and every web server of a
 * cluster sees the same value. The cache could not do this: reading and
 * writing back were two steps. Expired rows are removed by the cleanup job.
 */
final class DatabaseCounterStore implements CounterStore
{
    public function read(string $key): int
    {
        $count = \DBManager::get()->fetchColumn(
            'SELECT `count` FROM `app_connect_counters` WHERE `counter_key` = ? AND `expires_at` > ?',
            [$key, time()]
        );
        return $count === false || $count === null ? 0 : (int) $count;
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $now = time();
        $db = \DBManager::get();
        // An expired row starts over at one. LAST_INSERT_ID(expr) hands the
        // new count back on this connection, whatever others do meanwhile.
        $db->execute(
            'INSERT INTO `app_connect_counters` (`counter_key`, `count`, `expires_at`)
             VALUES (?, LAST_INSERT_ID(1), ?)
             ON DUPLICATE KEY UPDATE
                `count` = LAST_INSERT_ID(IF(`expires_at` > ?, `count` + 1, 1)),
                `expires_at` = IF(`expires_at` > ?, `expires_at`, VALUES(`expires_at`))',
            [$key, $now + $ttlSeconds, $now, $now]
        );
        return (int) $db->fetchColumn('SELECT LAST_INSERT_ID()');
    }

    /** Removes expired counters; returns how many. */
    public static function purge(int $now): int
    {
        return (int) \DBManager::get()->execute('DELETE FROM `app_connect_counters` WHERE `expires_at` <= ?', [$now]);
    }

    /** Removes the counters that name $userId (calendar key creations). */
    public static function forgetUser(string $userId): void
    {
        \DBManager::get()->execute(
            'DELETE FROM `app_connect_counters` WHERE `counter_key` LIKE ?',
            ['app-connect/ical-key-creations/' . addcslashes($userId, '%_\\') . '/%']
        );
    }
}
