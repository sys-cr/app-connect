<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * What the plugin knows about the configured logo: the address it belongs
 * to, the last good copy (if any), when that copy was fetched, when the
 * address was last tried and why that try failed.
 */
final class StoredLogo
{
    public function __construct(
        public readonly string $sourceUrl,
        public readonly ?LogoImage $image,
        public readonly ?int $fetchedAt,
        public readonly int $checkedAt,
        public readonly ?string $problem,
    ) {
    }
}
