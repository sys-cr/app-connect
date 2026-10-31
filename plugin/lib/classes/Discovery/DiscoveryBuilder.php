<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Discovery;

use AppConnect\Settings\Settings;

/**
 * Builds the discovery payload (contract/discovery.schema.json) from a fixed
 * allowlist of fields. Optional values that fail validation are left out
 * instead of being published.
 */
final class DiscoveryBuilder
{
    public const PAYLOAD_VERSION = 1;
    public const REDIRECT_URI = 'de.studapp2.open:/oauth2/callback';
    public const SCOPES = ['api'];

    /** Reported by every installation and not switchable. */
    public const BASE_FEATURES = ['courses', 'announcements', 'schedule', 'files', 'messages'];

    /** Core features the operator may switch on. */
    public const OPTIONAL_FEATURES = ['courseCatalogue', 'blubber', 'mensa'];

    /**
     * Core features that need an active core module; on by default, the
     * operator may switch them off.
     */
    public const MODULE_FEATURES = ['courseware'];

    /** Routes the plugin itself serves, announced with their path below `jsonapi.php/v1/`. */
    public const PLUGIN_FEATURES = [
        'eventPlaces' => ['version' => 1, 'route' => 'app-connect/course-dates/places'],
        'userLanguage' => ['version' => 1, 'route' => 'app-connect/user-language'],
        'userEvents' => ['version' => 1, 'route' => 'app-connect/user-events'],
        'webLogin' => ['version' => 1, 'route' => 'app-connect/web-login'],
        'icalKey' => ['version' => 1, 'route' => 'app-connect/me/ical-key'],
    ];

    private const LINK_TYPES = ['campusManagement', 'homepage', 'mensa', 'library', 'itHelp', 'privacyPolicy', 'other'];

    /**
     * @return array<string, mixed>
     */
    public function build(Settings $settings, InstanceInfo $instance): array
    {
        $base = $instance->baseUrl;
        $payload = [
            'payloadVersion' => self::PAYLOAD_VERSION,
            'instance' => $this->instanceBlock($settings, $instance),
            'oauth2' => [
                'clientId' => (string) $settings->clientId(),
                'authorizationEndpoint' => $base . '/dispatch.php/api/oauth2/authorize',
                'tokenEndpoint' => $base . '/jsonapi.php/v1/app-connect/token',
                'redirectUri' => self::REDIRECT_URI,
                'pkce' => 'S256',
                'scopes' => self::SCOPES,
            ],
        ];

        $links = $this->linksBlock($settings);
        $payload['features'] = $this->featuresBlock($settings, $instance, isset($links['mensa']));
        if ($settings->isPairingEnabled()) {
            $payload['pairing'] = ['version' => 1];
        }
        $optional = [
            'branding' => $this->brandingBlock($settings),
            'links' => $links,
            'sync' => $this->syncBlock($settings),
            'pollIntervals' => $this->pollIntervalsBlock($settings),
            'statistics' => $this->statisticsBlock($settings),
        ];
        foreach ($optional as $key => $block) {
            if ($block !== []) {
                $payload[$key] = $block;
            }
        }
        return $payload;
    }

    /** @return array<string, string> */
    private function instanceBlock(Settings $settings, InstanceInfo $instance): array
    {
        $configured = $settings->arrayValue(Settings::INSTANCE);
        $block = [
            'name' => self::text($configured['name'] ?? null, 200) ?? $instance->defaultName,
        ];
        $shortName = self::text($configured['shortName'] ?? null, 40);
        if ($shortName !== null) {
            $block['shortName'] = $shortName;
        }
        $email = $configured['supportEmail'] ?? null;
        if (is_string($email) && strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $block['supportEmail'] = $email;
        }
        $block['baseUrl'] = $instance->baseUrl;
        $block['jsonApiUrl'] = $instance->baseUrl . '/jsonapi.php/v1';
        $block['studipVersion'] = $instance->studipVersion;
        $block['pluginVersion'] = $instance->pluginVersion;
        if (preg_match('/^[a-z]{2}_[A-Z]{2}$/', $instance->locale) === 1) {
            $block['locale'] = $instance->locale;
        }
        return $block;
    }

    /** @return array<string, array<string, int|string>|false> */
    private function featuresBlock(Settings $settings, InstanceInfo $instance, bool $hasMensaLink): array
    {
        $switched = $settings->arrayValue(Settings::FEATURES);
        $features = [];
        foreach (self::BASE_FEATURES as $id) {
            // Messages can be switched off; the rest of the base set is always on.
            $features[$id] = $id === 'messages' && ($switched['messages'] ?? true) === false ? false : ['version' => 1];
        }
        foreach (self::OPTIONAL_FEATURES as $id) {
            $on = ($switched[$id] ?? false) === true;
            if ($id === 'mensa') {
                // The mensa widget needs a data source in `links.mensa`.
                $on = $on && $hasMensaLink;
            }
            $features[$id] = $on ? ['version' => 1] : false;
        }
        foreach (self::MODULE_FEATURES as $id) {
            // Stud.IP registers the module's routes only while the module is active.
            $on = $instance->coursewareModule && ($switched[$id] ?? true) !== false;
            $features[$id] = $on ? ['version' => 1] : false;
        }
        foreach (self::PLUGIN_FEATURES as $id => $feature) {
            $features[$id] = ($switched[$id] ?? true) === false ? false : $feature;
        }
        return $features;
    }

    /** @return array<string, mixed> */
    private function brandingBlock(Settings $settings): array
    {
        $configured = $settings->arrayValue(Settings::BRANDING);
        $block = [];
        if (self::isHexColor($configured['accentColor'] ?? null)) {
            $block['accentColor'] = $configured['accentColor'];
        }
        $gradient = $configured['gradient'] ?? null;
        if (is_array($gradient) && $gradient !== [] && count($gradient) <= 3
            && count(array_filter($gradient, [self::class, 'isHexColor'])) === count($gradient)) {
            $block['gradient'] = array_values($gradient);
        }
        if (self::isHttpsUrl($configured['logoUrl'] ?? null)) {
            $block['logoUrl'] = $configured['logoUrl'];
        }
        return $block;
    }

    /** @return array<string, mixed> */
    private function linksBlock(Settings $settings): array
    {
        $configured = $settings->arrayValue(Settings::LINKS);
        $external = [];
        foreach (is_array($configured['external'] ?? null) ? $configured['external'] : [] as $link) {
            if (!is_array($link) || !in_array($link['type'] ?? null, self::LINK_TYPES, true)
                || !self::isHttpsUrl($link['url'] ?? null) || ($link['type'] === 'privacyPolicy')) {
                continue;
            }
            $entry = ['type' => $link['type']];
            $label = self::text($link['label'] ?? null, 80);
            if ($label !== null) {
                $entry['label'] = $label;
            }
            $entry['url'] = $link['url'];
            $external[] = $entry;
        }
        // The privacy policy has its own setting so it cannot be entered twice.
        $privacy = $settings->stringValue(Settings::PRIVACY_POLICY_URL);
        if (self::isHttpsUrl($privacy)) {
            $external[] = ['type' => 'privacyPolicy', 'url' => $privacy];
        }

        $block = [];
        if ($external !== []) {
            $block['external'] = $external;
        }
        $mensa = $this->mensaLink($configured['mensa'] ?? null);
        if ($mensa !== null) {
            $block['mensa'] = $mensa;
        }
        return $block;
    }

    /** @return array<string, mixed>|null */
    private function mensaLink(mixed $mensa): ?array
    {
        if (!is_array($mensa)) {
            return null;
        }
        if (($mensa['provider'] ?? null) === 'openmensa') {
            $ids = $mensa['canteenIds'] ?? null;
            if (is_array($ids) && $ids !== []
                && count(array_filter($ids, static fn ($id): bool => is_int($id) && $id >= 0)) === count($ids)) {
                return ['provider' => 'openmensa', 'canteenIds' => array_values($ids)];
            }
            return null;
        }
        if (($mensa['provider'] ?? null) === 'link' && self::isHttpsUrl($mensa['url'] ?? null)) {
            return ['provider' => 'link', 'url' => $mensa['url']];
        }
        return null;
    }

    /** @return array<string, int> */
    private function syncBlock(Settings $settings): array
    {
        $minutes = $settings->arrayValue(Settings::INTERVALS)['sync']['backgroundIntervalMinutes'] ?? null;
        return is_int($minutes) && $minutes >= 15 ? ['backgroundIntervalMinutes' => $minutes] : [];
    }

    /** @return array<string, int> */
    private function pollIntervalsBlock(Settings $settings): array
    {
        $configured = $settings->arrayValue(Settings::INTERVALS)['pollIntervals'] ?? [];
        $block = [];
        foreach (['blubberChannel', 'blubberList', 'blubberMaxBackoff'] as $key) {
            $seconds = is_array($configured) ? ($configured[$key] ?? null) : null;
            if (is_int($seconds) && $seconds >= 5 && $seconds <= 600) {
                $block[$key] = $seconds;
            }
        }
        return $block;
    }

    /** @return array<string, string> */
    private function statisticsBlock(Settings $settings): array
    {
        $configured = $settings->arrayValue(Settings::STATISTICS);
        $siteId = self::text(is_scalar($configured['siteId'] ?? null) ? (string) $configured['siteId'] : null, 32);
        if (!self::isHttpsUrl($configured['matomoUrl'] ?? null) || $siteId === null) {
            return [];
        }
        return ['matomoUrl' => $configured['matomoUrl'], 'siteId' => $siteId];
    }

    private static function text(mixed $value, int $maxLength): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return $value !== '' && mb_strlen($value) <= $maxLength ? $value : null;
    }

    private static function isHexColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }

    private static function isHttpsUrl(mixed $value): bool
    {
        return is_string($value) && preg_match('~^https://[^\s/?#@]+(/\S*)?$~', $value) === 1;
    }
}
