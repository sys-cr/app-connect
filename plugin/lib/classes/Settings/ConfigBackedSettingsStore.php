<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/**
 * Settings store that keeps the operator settings in the Stud.IP
 * configuration ({@see OperatorConfig}) and everything else – the client id
 * and values without an editor yet – in the plugin table. Addresses that are
 * not https are treated as unset, because the core editor cannot refuse them.
 */
final class ConfigBackedSettingsStore implements SettingsStore
{
    /** Setting keys that live in the configuration. */
    public const OPERATOR_KEYS = [
        Settings::ENABLED,
        Settings::PAIRING_ENABLED,
        Settings::PROFILE_ENTRY,
        Settings::INSTANCE,
        Settings::PRIVACY_POLICY_URL,
        Settings::STORE_LINKS,
        Settings::FEATURES,
        Settings::BRANDING,
        Settings::LINKS,
    ];

    public function __construct(
        private readonly ConfigAccess $config,
        private readonly SettingsStore $table,
    ) {
    }

    public function get(string $key): mixed
    {
        return match ($key) {
            Settings::ENABLED => (bool) $this->config->get(OperatorConfig::ENABLED),
            Settings::PAIRING_ENABLED => (bool) $this->config->get(OperatorConfig::PAIRING_ENABLED),
            Settings::PROFILE_ENTRY => $this->config->get(OperatorConfig::PROFILE_ENTRY) !== false,
            Settings::INSTANCE => $this->instance(),
            Settings::PRIVACY_POLICY_URL => $this->url(OperatorConfig::PRIVACY_POLICY_URL),
            Settings::STORE_LINKS => $this->storeLinks(),
            Settings::FEATURES => array_map(
                fn (string $field): bool => $this->flag($field),
                OperatorConfig::FEATURE_FIELDS
            ),
            Settings::BRANDING => $this->branding(),
            Settings::LINKS => $this->links(),
            default => $this->table->get($key),
        };
    }

    public function set(string $key, mixed $value): void
    {
        $array = is_array($value) ? $value : [];
        match ($key) {
            Settings::ENABLED => $this->config->set(OperatorConfig::ENABLED, $value === true),
            Settings::PAIRING_ENABLED => $this->config->set(OperatorConfig::PAIRING_ENABLED, $value === true),
            Settings::PROFILE_ENTRY => $this->config->set(OperatorConfig::PROFILE_ENTRY, $value !== false),
            Settings::INSTANCE => $this->setStrings([
                'name' => OperatorConfig::INSTANCE_NAME,
                'shortName' => OperatorConfig::INSTANCE_SHORT_NAME,
                'supportEmail' => OperatorConfig::SUPPORT_EMAIL,
            ], $array),
            Settings::PRIVACY_POLICY_URL => $this->config->set(
                OperatorConfig::PRIVACY_POLICY_URL,
                is_string($value) ? $value : ''
            ),
            Settings::STORE_LINKS => $this->setStrings(OperatorConfig::STORE_FIELDS, $array),
            Settings::FEATURES => $this->setFeatures($array),
            Settings::BRANDING => $this->setBranding($array),
            Settings::LINKS => $this->setLinks($array),
            default => $this->table->set($key, $value),
        };
    }

    /** @return array<string, string>|null */
    private function instance(): ?array
    {
        $email = $this->text(OperatorConfig::SUPPORT_EMAIL);
        $instance = array_filter([
            'name' => $this->text(OperatorConfig::INSTANCE_NAME),
            'shortName' => $this->text(OperatorConfig::INSTANCE_SHORT_NAME),
            'supportEmail' => OperatorConfig::isEmail($email) ? $email : null,
        ], static fn (?string $value): bool => $value !== null);
        return $instance === [] ? null : $instance;
    }

    /** A switch; unset means its default from the definitions. */
    private function flag(string $field): bool
    {
        $value = $this->config->get($field);
        return $value === null ? (bool) (OperatorConfig::definitions()[$field]['value'] ?? false) : (bool) $value;
    }

    /** @return array<string, string>|null */
    private function branding(): ?array
    {
        $accent = $this->text(OperatorConfig::ACCENT_COLOR);
        $branding = array_filter([
            'accentColor' => OperatorConfig::isHexColor($accent) ? strtoupper((string) $accent) : null,
            'logoUrl' => $this->url(OperatorConfig::LOGO_URL),
        ], static fn (?string $value): bool => $value !== null);
        return $branding === [] ? null : $branding;
    }

    /** @return array<string, mixed>|null */
    private function links(): ?array
    {
        $external = [];
        foreach (OperatorConfig::LINK_FIELDS as $type => $field) {
            $url = $this->url($field);
            if ($url !== null) {
                $external[] = ['type' => $type, 'url' => $url];
            }
        }
        $links = [];
        if ($external !== []) {
            $links['external'] = $external;
        }
        $ids = OperatorConfig::canteenIds($this->text(OperatorConfig::MENSA_OPENMENSA_IDS));
        $mensaUrl = $this->url(OperatorConfig::MENSA_URL);
        if ($ids !== null) {
            $links['mensa'] = ['provider' => 'openmensa', 'canteenIds' => $ids];
        } elseif ($mensaUrl !== null) {
            $links['mensa'] = ['provider' => 'link', 'url' => $mensaUrl];
        }
        return $links === [] ? null : $links;
    }

    /** @param array<string, mixed> $branding */
    private function setBranding(array $branding): void
    {
        $this->config->set(OperatorConfig::ACCENT_COLOR, is_string($branding['accentColor'] ?? null) ? $branding['accentColor'] : '');
        $this->config->set(OperatorConfig::LOGO_URL, is_string($branding['logoUrl'] ?? null) ? $branding['logoUrl'] : '');
    }

    /** @param array<string, mixed> $links */
    private function setLinks(array $links): void
    {
        $byType = [];
        foreach (is_array($links['external'] ?? null) ? $links['external'] : [] as $link) {
            if (is_array($link) && is_string($link['type'] ?? null) && is_string($link['url'] ?? null)) {
                $byType[$link['type']] ??= $link['url'];
            }
        }
        foreach (OperatorConfig::LINK_FIELDS as $type => $field) {
            $this->config->set($field, $byType[$type] ?? '');
        }
        $mensa = is_array($links['mensa'] ?? null) ? $links['mensa'] : [];
        $ids = ($mensa['provider'] ?? null) === 'openmensa' && is_array($mensa['canteenIds'] ?? null)
            ? implode(',', array_map('intval', $mensa['canteenIds'])) : '';
        $url = ($mensa['provider'] ?? null) === 'link' && is_string($mensa['url'] ?? null) ? $mensa['url'] : '';
        $this->config->set(OperatorConfig::MENSA_OPENMENSA_IDS, $ids);
        $this->config->set(OperatorConfig::MENSA_URL, $url);
    }

    /** @return array<string, string>|null */
    private function storeLinks(): ?array
    {
        $links = array_filter(
            array_map(fn (string $field): ?string => $this->url($field), OperatorConfig::STORE_FIELDS),
            static fn (?string $value): bool => $value !== null
        );
        return $links === [] ? null : $links;
    }

    private function url(string $field): ?string
    {
        $value = $this->text($field);
        return OperatorConfig::isHttpsUrl($value) ? $value : null;
    }

    private function text(string $field): ?string
    {
        $value = $this->config->get($field);
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param array<string, string> $fields key in the setting → configuration field
     * @param array<string, mixed> $values
     */
    private function setStrings(array $fields, array $values): void
    {
        foreach ($fields as $key => $field) {
            $value = $values[$key] ?? '';
            $this->config->set($field, is_string($value) ? $value : '');
        }
    }

    /** @param array<string, mixed> $features */
    private function setFeatures(array $features): void
    {
        foreach (OperatorConfig::FEATURE_FIELDS as $id => $field) {
            $this->config->set($field, ($features[$id] ?? false) === true);
        }
    }
}
