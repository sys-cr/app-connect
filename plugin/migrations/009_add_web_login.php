<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * Web login from the app: the ticket table (hashes only), its event log
 * actions and the switch in the section "Stud.App".
 */
return new class extends Migration
{
    /** Action name => [description, info template]. */
    public const ACTIONS = [
        'APPCONNECT_WEB_LOGIN_ISSUE' => [
            'Web-Anmeldung aus der App angefordert',
            '%user fordert aus der App eine Anmeldung im Web an.',
        ],
        'APPCONNECT_WEB_LOGIN_REDEEM' => [
            'Web-Anmeldung aus der App eingelöst',
            '%user meldet sich aus der App im Web an.',
        ],
        'APPCONNECT_WEB_LOGIN_REJECT' => [
            'Web-Anmeldung aus der App abgelehnt',
            'Eine Anmeldung von %user aus der App im Web wird abgelehnt.',
        ],
    ];

    public const FIELDS = [
        'APP_CONNECT_FEATURE_WEB_LOGIN' => [
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Öffnet Stud.IP-Seiten aus der App ohne erneute Anmeldung im Browser (einmaliger Link, 60 Sekunden gültig). Ein zweiter Faktor des Identity Providers wird dabei nicht abgefragt.',
        ],
    ];

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
        foreach (self::ACTIONS as $name => [$description, $template]) {
            if (!LogAction::findOneByName($name)) {
                StudipLog::registerActionPlugin($name, $description, $template, 'AppConnect');
            }
        }
        foreach (self::FIELDS as $field => $definition) {
            if (!Config::get()->getMetadata($field)) {
                Config::get()->create($field, $definition + ['range' => 'global', 'section' => 'Stud.App']);
            }
        }
    }

    protected function down()
    {
        foreach (array_keys(self::FIELDS) as $field) {
            Config::get()->delete($field);
        }
        foreach (array_keys(self::ACTIONS) as $name) {
            StudipLog::unregisterAction($name);
        }
        DBManager::get()->exec('DROP TABLE IF EXISTS `app_connect_web_login_tickets`');
    }
};
