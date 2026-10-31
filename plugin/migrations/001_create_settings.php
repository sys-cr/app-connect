<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Audit\EventLog;

return new class extends Migration
{
    public function description()
    {
        return 'Creates the settings table of app-connect and registers its event log actions.';
    }

    protected function up()
    {
        DBManager::get()->exec(
            "CREATE TABLE IF NOT EXISTS `app_connect_settings` (
                `setting_key` VARCHAR(64) NOT NULL,
                `value` LONGTEXT NOT NULL,
                `chdate` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        foreach (EventLog::ACTIONS as $name => [$description, $template]) {
            StudipLog::registerActionPlugin($name, $description, $template, 'AppConnect');
        }
    }

    protected function down()
    {
        foreach (array_keys(EventLog::ACTIONS) as $name) {
            StudipLog::unregisterAction($name);
        }
        DBManager::get()->exec('DROP TABLE IF EXISTS `app_connect_settings`');
    }
};
