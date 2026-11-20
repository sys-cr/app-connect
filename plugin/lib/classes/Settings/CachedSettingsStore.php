<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/**
 * Keeps the values of another store once read, for one request: the plugin
 * is a system plugin, so the settings are read on every page. Writes go
 * through and update the copy.
 */
final class CachedSettingsStore implements SettingsStore
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function __construct(private readonly SettingsStore $store)
    {
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->values)) {
            $this->values[$key] = $this->store->get($key);
        }
        return $this->values[$key];
    }

    public function set(string $key, mixed $value): void
    {
        $this->store->set($key, $value);
        $this->values[$key] = $value;
    }
}
