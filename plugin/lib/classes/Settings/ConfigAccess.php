<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/** Read and write access to Stud.IP configuration entries. */
interface ConfigAccess
{
    public function get(string $field): mixed;

    public function set(string $field, mixed $value): void;
}
