<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/** Where the copy of the logo lives: one record at most. */
interface LogoStore
{
    public function load(): ?StoredLogo;

    public function save(StoredLogo $logo): void;

    public function delete(): void;
}
