<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/** Settings kept in memory, for tests. */
final class InMemorySettingsStore implements SettingsStore
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values = [])
    {
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        if ($value === null) {
            unset($this->values[$key]);
            return;
        }
        $this->values[$key] = $value;
    }
}
