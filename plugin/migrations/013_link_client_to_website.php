<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * "Entwickelt durch" on the core page "Drittanwendungen" links to the
 * homepage of the app client, which so far was the instance's own address.
 * It now points to the website of the apps, and the description loses its
 * dash. The instance's address served as a fallback for the base URL; where
 * none was recorded yet, it is kept as the recorded base URL first.
 */
return new class extends Migration
{
    public const HOMEPAGE = 'https://studapp2.de';
    public const DESCRIPTION = 'Mobile apps. Managed by the app-connect plugin. Please do not edit it here.';

    public function description()
    {
        return 'Links the app client to the website of the apps and updates its description.';
    }

    protected function up()
    {
        $db = DBManager::get();
        $clientId = $this->setting('oauth_client_id');
        if (!is_scalar($clientId) || (string) $clientId === '') {
            return;
        }
        $homepage = $db->fetchColumn('SELECT `homepage` FROM `oauth2_clients` WHERE `id` = ?', [(string) $clientId]);
        if (!self::isBaseUrl($this->setting('base_url')) && self::isBaseUrl($homepage) && $homepage !== self::HOMEPAGE) {
            $db->execute(
                'REPLACE INTO `app_connect_settings` (`setting_key`, `value`, `chdate`) VALUES (?, ?, UNIX_TIMESTAMP())',
                ['base_url', json_encode(rtrim((string) $homepage, '/'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
            );
        }
        $db->execute(
            'UPDATE `oauth2_clients` SET `homepage` = ?, `description` = ?, `chdate` = UNIX_TIMESTAMP() WHERE `id` = ?',
            [self::HOMEPAGE, self::DESCRIPTION, (string) $clientId]
        );
    }

    protected function down()
    {
        // The instance's address stays recorded as the base URL; the old
        // link and description are not restored.
    }

    private function setting(string $key): mixed
    {
        $value = DBManager::get()->fetchColumn(
            'SELECT `value` FROM `app_connect_settings` WHERE `setting_key` = ?',
            [$key]
        );
        return is_string($value) ? json_decode($value, true) : null;
    }

    private static function isBaseUrl(mixed $value): bool
    {
        return is_string($value) && preg_match('~^https?://[^\s/?#@]+(/[^\s?#]*)?$~', $value) === 1;
    }
};
