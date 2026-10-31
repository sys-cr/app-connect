<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Audit\EventLog;
use AppConnect\Settings\OperatorConfig;

/**
 * Web login from the app: the ticket table (hashes only), its event log
 * actions and the switch in the section "Stud.App".
 */
return new class extends Migration
{
    public function description()
    {
        return 'Adds the web-login tickets of app-connect and their switch.';
    }

    protected function up()
    {
        DBManager::get()->exec(
            "CREATE TABLE IF NOT EXISTS `app_connect_web_login_tickets` (
                `ticket_hash` CHAR(64) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `user_id` CHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL,
                `return_to` VARCHAR(2048) CHARACTER SET latin1 COLLATE latin1_bin NULL,
                `access_token_id` VARCHAR(100) NULL,
                `device_row_id` BIGINT UNSIGNED NULL,
                `created_at` INT UNSIGNED NOT NULL,
                `expires_at` INT UNSIGNED NOT NULL,
                `used_at` INT UNSIGNED NULL,
                PRIMARY KEY (`ticket_hash`),
                KEY `user_created` (`user_id`, `created_at`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        foreach (EventLog::WEB_LOGIN_ACTIONS as $name) {
            if (!LogAction::findOneByName($name)) {
                [$description, $template] = EventLog::ACTIONS[$name];
                StudipLog::registerActionPlugin($name, $description, $template, 'AppConnect');
            }
        }

        $definition = OperatorConfig::definitions()[OperatorConfig::FEATURE_WEB_LOGIN];
        if (!Config::get()->getMetadata(OperatorConfig::FEATURE_WEB_LOGIN)) {
            Config::get()->create(OperatorConfig::FEATURE_WEB_LOGIN, [
                'value' => (int) $definition['value'],
                'type' => $definition['type'],
                'range' => 'global',
                'section' => OperatorConfig::SECTION,
                'description' => $definition['description'],
            ]);
        }
    }

    protected function down()
    {
        Config::get()->delete(OperatorConfig::FEATURE_WEB_LOGIN);
        foreach (EventLog::WEB_LOGIN_ACTIONS as $name) {
            StudipLog::unregisterAction($name);
        }
        DBManager::get()->exec('DROP TABLE IF EXISTS `app_connect_web_login_tickets`');
    }
};
