<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/** {@see ConfigAccess} on the running Stud.IP (`Config::get()`). */
final class StudipConfigAccess implements ConfigAccess
{
    public function get(string $field): mixed
    {
        return \Config::get()->getValue($field);
    }

    public function set(string $field, mixed $value): void
    {
        \Config::get()->store($field, $value);
    }
}
