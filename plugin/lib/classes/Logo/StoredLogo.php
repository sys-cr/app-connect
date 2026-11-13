<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * What the plugin knows about the logo: the address it belongs to (or
 * {@see self::UPLOAD}), the last good copy (if any), when that copy was
 * fetched or uploaded, when it was last tried and why that try failed.
 */
final class StoredLogo
{
    /**
     * Source of a logo the operator uploaded. It can never be a configured
     * address, which always starts with https://.
     */
    public const UPLOAD = 'upload';

    public function __construct(
        public readonly string $sourceUrl,
        public readonly ?LogoImage $image,
        public readonly ?int $fetchedAt,
        public readonly int $checkedAt,
        public readonly ?string $problem,
    ) {
    }

    /** Whether the operator uploaded this logo instead of naming an address. */
    public function isUpload(): bool
    {
        return $this->sourceUrl === self::UPLOAD;
    }
}
