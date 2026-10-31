<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/**
 * Raw key/value storage behind {@see Settings}. Values are plain PHP data that
 * survive a JSON round trip.
 */
interface SettingsStore
{
    /** Returns the stored value or null when the key has never been set. */
    public function get(string $key): mixed;

    /** Stores a value; null removes the key. */
    public function set(string $key, mixed $value): void;
}
