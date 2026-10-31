<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

use AppConnect\Discovery\InstanceInfo;
use AppConnect\OAuth2\ClientProvisioner;
use AppConnect\Settings\ConfigBackedSettingsStore;
use AppConnect\Settings\DatabaseSettingsStore;
use AppConnect\Settings\Settings;
use AppConnect\Settings\StudipConfigAccess;

/**
 * Wiring to the running Stud.IP: settings from the configuration section
 * "Stud.App" and the plugin table, and the facts about the instance the
 * discovery needs.
 */
final class Runtime
{
    public static function settings(): Settings
    {
        return new Settings(new ConfigBackedSettingsStore(new StudipConfigAccess(), new DatabaseSettingsStore()));
    }

    /** Base URL of the instance without trailing slash. */
    public static function baseUrl(): string
    {
        return rtrim((string) $GLOBALS['ABSOLUTE_URI_STUDIP'], '/');
    }

    /**
     * Base URL for links that leave the request, such as the address in the
     * connection mail: the one recorded when root switched the connection
     * on, else the homepage of the app client, else the request's. Without
     * ABSOLUTE_URI_STUDIP the core derives the request's from the Host header.
     */
    public static function canonicalBaseUrl(): string
    {
        $settings = self::settings();
        $recorded = $settings->stringValue(Settings::BASE_URL);
        if (ClientProvisioner::isBaseUrl($recorded)) {
            return rtrim((string) $recorded, '/');
        }
        $homepage = (new ClientProvisioner($settings))->storedClient()?->homepage;
        if (ClientProvisioner::isBaseUrl($homepage)) {
            return rtrim((string) $homepage, '/');
        }
        return self::baseUrl();
    }

    public static function instanceInfo(): InstanceInfo
    {
        return new InstanceInfo(
            self::baseUrl(),
            (string) ($GLOBALS['UNI_NAME_CLEAN'] ?? 'Stud.IP'),
            (string) ($GLOBALS['SOFTWARE_VERSION'] ?? \StudipVersion::getStudipVersion(false)),
            self::pluginVersion(),
            (string) (\Config::get()->getValue('DEFAULT_LANGUAGE') ?? ''),
            self::coursewareModuleActive(),
        );
    }

    /**
     * Whether the core module Courseware is active. Stud.IP registers the
     * Courseware routes of the JSON:API only then.
     */
    public static function coursewareModuleActive(): bool
    {
        return class_exists(\CoursewareModule::class)
            && \PluginManager::getInstance()->getPlugin(\CoursewareModule::class) !== null;
    }

    /** Version from plugin.manifest. */
    public static function pluginVersion(): string
    {
        $manifest = @file(dirname(__DIR__, 2) . '/plugin.manifest', FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($manifest as $line) {
            if (str_starts_with($line, 'version=')) {
                return trim(substr($line, strlen('version=')));
            }
        }
        return '0.0.0';
    }
}
