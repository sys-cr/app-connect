<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

use AppConnect\Discovery\InstanceInfo;
use AppConnect\Logo\CurlHttpFetcher;
use AppConnect\Logo\DatabaseLogoStore;
use AppConnect\Logo\LogoFetch;
use AppConnect\Logo\LogoService;
use AppConnect\OAuth2\ClientProvisioner;
use AppConnect\Settings\ConfigBackedSettingsStore;
use AppConnect\Settings\DatabaseSettingsStore;
use AppConnect\Settings\Settings;
use AppConnect\Settings\StudipConfigAccess;

/**
 * Wiring to the running Stud.IP: settings from the configuration section
 * "Stud.App" and the plugin table, the copy of the institution logo, and
 * the facts about the instance the discovery needs.
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
     * on, else the request's. Without ABSOLUTE_URI_STUDIP the core derives
     * the request's from the Host header.
     */
    public static function canonicalBaseUrl(): string
    {
        $recorded = self::settings()->stringValue(Settings::BASE_URL);
        if (ClientProvisioner::isBaseUrl($recorded)) {
            return rtrim((string) $recorded, '/');
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
            self::logoVersion(),
        );
    }

    /**
     * Content version of the logo copy for the discovery. A missing table
     * (an update whose migrations have not run yet) leaves the logo out
     * instead of breaking the discovery.
     */
    private static function logoVersion(): ?string
    {
        try {
            return self::logo()->current(self::configuredLogoUrl())?->version();
        } catch (\PDOException) {
            return null;
        }
    }

    /** The logo address from the section "Stud.App" when it is https, else null. */
    public static function configuredLogoUrl(): ?string
    {
        $url = self::settings()->arrayValue(Settings::BRANDING)['logoUrl'] ?? null;
        return is_string($url) ? $url : null;
    }

    /**
     * The plugin's copy of the logo. Fetches go out over the core's
     * HTTP_PROXY unless the host is listed in HTTP_PROXY_IGNORE, as the
     * core's own requests do.
     *
     * @param string|null $url the address about to be fetched; the configured one when null
     */
    public static function logo(?string $url = null): LogoService
    {
        $host = (string) parse_url(self::baseUrl(), PHP_URL_HOST);
        $proxy = self::proxyFor($url ?? self::configuredLogoUrl());
        return new LogoService(
            new DatabaseLogoStore(),
            new LogoFetch(new CurlHttpFetcher($proxy), self::resolver(), $host)
        );
    }

    /** host:port of the core's proxy for $url, or null for a direct connection. */
    private static function proxyFor(?string $url): ?string
    {
        $proxy = trim((string) \Config::get()->getValue('HTTP_PROXY'));
        if ($proxy === '' || $url === null) {
            return null;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $ignore = array_filter(array_map('trim', explode(',', (string) \Config::get()->getValue('HTTP_PROXY_IGNORE'))));
        foreach ($ignore as $pattern) {
            if (fnmatch($pattern, $host)) {
                return null;
            }
        }
        return $proxy;
    }

    /** @return \Closure(string): list<string> the IPv4 and IPv6 addresses of a host name */
    private static function resolver(): \Closure
    {
        return static function (string $host): array {
            $addresses = @gethostbynamel($host) ?: [];
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
            return array_values(array_unique($addresses));
        };
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
