<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * The counters of the limits move from the cache into a table, where one
 * statement adds one: concurrent requests no longer overwrite each other.
 * Keys hold a keyed HMAC of the address or a person's id, never an address.
 */
return new class extends Migration
{
    public function description()
    {
        return 'Creates the table of the app-connect limit counters.';
    }

    protected function up()
    {
        DBManager::get()->exec(
            "CREATE TABLE IF NOT EXISTS `app_connect_counters` (
                `counter_key` VARCHAR(191) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `count` INT UNSIGNED NOT NULL,
                `expires_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`counter_key`),
                KEY `expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    protected function down()
    {
        DBManager::get()->exec('DROP TABLE IF EXISTS `app_connect_counters`');
    }
};
