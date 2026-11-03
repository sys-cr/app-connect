<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

return new class extends Migration
{
    /** Action name => [description, info template]. */
    public const ACTIONS = [
        'APPCONNECT_ENABLE' => ['App-Anbindung eingeschaltet', '%user schaltet die App-Anbindung ein.'],
        'APPCONNECT_DISABLE' => ['App-Anbindung ausgeschaltet', '%user schaltet die App-Anbindung aus.'],
        'APPCONNECT_CLIENT_CREATE' => [
            'OAuth2-Client der App angelegt',
            '%user legt den OAuth2-Client der App an (%info).',
        ],
        'APPCONNECT_CLIENT_REUSE' => [
            'OAuth2-Client der App wiederverwendet',
            '%user verwendet den OAuth2-Client der App wieder (%info).',
        ],
        'APPCONNECT_SETTINGS' => [
            'Einstellungen der App-Anbindung geändert',
            '%user ändert Einstellungen der App-Anbindung (%info).',
        ],
    ];

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
        foreach (self::ACTIONS as $name => [$description, $template]) {
            if (!LogAction::findOneByName($name)) {
                StudipLog::registerActionPlugin($name, $description, $template, 'AppConnect');
            }
        }
    }

    protected function down()
    {
        // Uninstalling drops the table that holds the client id, so the app
        // client is revoked first; otherwise its tokens would stay valid.
        $db = DBManager::get();
        $value = $db->fetchColumn(
            "SELECT `value` FROM `app_connect_settings` WHERE `setting_key` = 'oauth_client_id'"
        );
        $clientId = is_string($value) ? json_decode($value, true) : null;
        if (is_scalar($clientId) && (string) $clientId !== '') {
            $db->execute(
                'UPDATE `oauth2_clients` SET `revoked` = 1, `chdate` = UNIX_TIMESTAMP() WHERE `id` = ?',
                [(string) $clientId]
            );
        }
        foreach (array_keys(self::ACTIONS) as $name) {
            StudipLog::unregisterAction($name);
        }
        $db->exec('DROP TABLE IF EXISTS `app_connect_settings`');
    }
};
