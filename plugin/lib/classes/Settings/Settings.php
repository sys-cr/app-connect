<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

use InvalidArgumentException;

/**
 * Typed access to the plugin settings. Only the keys in {@see self::KEYS} exist;
 * everything the discovery publishes is read through this class, never by
 * serialising the table.
 */
final class Settings
{
    public const ENABLED = 'enabled';
    public const PROFILE_ENTRY = 'profile_entry';
    public const PAIRING_ENABLED = 'pairing_enabled';
    public const STORE_LINKS = 'store_links';
    public const OAUTH_CLIENT_ID = 'oauth_client_id';
    public const INSTANCE = 'instance';
    public const BRANDING = 'branding';
    public const LINKS = 'links';
    public const PRIVACY_POLICY_URL = 'privacy_policy_url';
    public const INTERVALS = 'intervals';
    public const STATISTICS = 'statistics';
    public const FEATURES = 'features';
    public const PRIVACY_SNIPPET = 'privacy_snippet';
    /** Base URL recorded when root switched the app connection on. */
    public const BASE_URL = 'base_url';

    /** The allowlist of setting keys. */
    public const KEYS = [
        self::ENABLED,
        self::PROFILE_ENTRY,
        self::PAIRING_ENABLED,
        self::STORE_LINKS,
        self::OAUTH_CLIENT_ID,
        self::INSTANCE,
        self::BRANDING,
        self::LINKS,
        self::PRIVACY_POLICY_URL,
        self::INTERVALS,
        self::STATISTICS,
        self::FEATURES,
        self::PRIVACY_SNIPPET,
        self::BASE_URL,
    ];

    public function __construct(private readonly SettingsStore $store)
    {
    }

    public function get(string $key): mixed
    {
        $this->assertKnown($key);
        return $this->store->get($key);
    }

    public function set(string $key, mixed $value): void
    {
        $this->assertKnown($key);
        $this->store->set($key, $value);
    }

    public function isEnabled(): bool
    {
        return $this->store->get(self::ENABLED) === true;
    }

    public function isPairingEnabled(): bool
    {
        return $this->store->get(self::PAIRING_ENABLED) === true;
    }

    /** The profile entry is on unless the operator switched it off. */
    public function isProfileEntryEnabled(): bool
    {
        return $this->store->get(self::PROFILE_ENTRY) !== false;
    }

    /**
     * Whether the web login from the app is available: the app connection is
     * on, the app client exists and the operator did not switch it off.
     */
    public function isWebLoginEnabled(): bool
    {
        return $this->isEnabled() && $this->clientId() !== null
            && ($this->arrayValue(self::FEATURES)['webLogin'] ?? true) !== false;
    }

    public function clientId(): ?string
    {
        $id = $this->store->get(self::OAUTH_CLIENT_ID);
        return is_scalar($id) && (string) $id !== '' ? (string) $id : null;
    }

    /** @return array<string, mixed> */
    public function arrayValue(string $key): array
    {
        $value = $this->get($key);
        return is_array($value) ? $value : [];
    }

    public function stringValue(string $key): ?string
    {
        $value = $this->get($key);
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function assertKnown(string $key): void
    {
        if (!in_array($key, self::KEYS, true)) {
            throw new InvalidArgumentException('Unknown setting');
        }
    }
}
