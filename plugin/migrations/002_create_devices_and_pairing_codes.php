<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

return new class extends Migration
{
    public function description()
    {
        return 'Creates the device and pairing code tables of app-connect.';
    }

    protected function up()
    {
        $db = DBManager::get();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `app_connect_devices` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `device_id` CHAR(36) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `user_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `name` VARCHAR(64) NOT NULL,
                `platform` ENUM('ios','android') NOT NULL,
                `paired_via` ENUM('pairing','browser') NOT NULL,
                `access_token_id` VARCHAR(100) NOT NULL,
                `refresh_token_id` VARCHAR(100) NULL,
                `bound_at` INT UNSIGNED NOT NULL,
                `paired_at` INT UNSIGNED NOT NULL,
                `revoked_at` INT UNSIGNED NULL,
                `mkdate` INT UNSIGNED NOT NULL,
                `chdate` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `refresh_token_id` (`refresh_token_id`),
                KEY `user_id` (`user_id`),
                KEY `device_id` (`device_id`),
                KEY `access_token_id` (`access_token_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `app_connect_pairing_codes` (
                `code_hash` CHAR(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `user_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `expires_at` INT UNSIGNED NOT NULL,
                `consumed_at` INT UNSIGNED NULL,
                `device_id` CHAR(36) CHARACTER SET latin1 COLLATE latin1_bin NULL,
                `mkdate` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`code_hash`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    protected function down()
    {
        DBManager::get()->exec('DROP TABLE IF EXISTS `app_connect_pairing_codes`');
        DBManager::get()->exec('DROP TABLE IF EXISTS `app_connect_devices`');
    }
};
