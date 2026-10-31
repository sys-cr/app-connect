<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/**
 * Settings in the plugin table `app_connect_settings`, one JSON value per key.
 */
final class DatabaseSettingsStore implements SettingsStore
{
    public function get(string $key): mixed
    {
        $row = SettingRecord::find($key);
        if ($row === null) {
            return null;
        }
        return json_decode((string) $row->value, true, 32, JSON_THROW_ON_ERROR);
    }

    public function set(string $key, mixed $value): void
    {
        $row = SettingRecord::find($key);
        if ($value === null) {
            $row?->delete();
            return;
        }
        $row ??= new SettingRecord($key);
        $row->value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $row->store();
    }
}
