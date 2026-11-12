<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/** {@see LogoStore} in memory, for tests. */
final class InMemoryLogoStore implements LogoStore
{
    public function __construct(private ?StoredLogo $logo = null)
    {
    }

    public function load(): ?StoredLogo
    {
        return $this->logo;
    }

    public function save(StoredLogo $logo): void
    {
        $this->logo = $logo;
    }

    public function delete(): void
    {
        $this->logo = null;
    }
}
