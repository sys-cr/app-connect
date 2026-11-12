<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * A logo the plugin serves: PNG, JPEG or WebP, recognised by its bytes and
 * never by a header, at most 512 KB and between 32 and 2048 pixels per side.
 * SVG is refused: the apps decode raster pictures only, and an SVG can carry
 * script.
 */
final class LogoImage
{
    public const MAX_BYTES = 512 * 1024;
    public const MIN_SIDE = 32;
    public const MAX_SIDE = 2048;

    /** Media type → the IMAGETYPE_* that getimagesize must report for it. */
    private const TYPES = [
        'image/png' => IMAGETYPE_PNG,
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    private function __construct(
        public readonly string $bytes,
        public readonly string $mimeType,
        public readonly int $width,
        public readonly int $height,
        public readonly string $hash,
    ) {
    }

    /** @throws LogoRejected */
    public static function fromBytes(string $bytes): self
    {
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new LogoRejected(LogoRejected::TOO_LARGE);
        }
        $mimeType = self::sniff($bytes);
        if ($mimeType === null) {
            throw new LogoRejected(LogoRejected::TYPE);
        }
        $info = @getimagesizefromstring($bytes);
        if (!is_array($info) || ($info[2] ?? null) !== self::TYPES[$mimeType]) {
            throw new LogoRejected(LogoRejected::BROKEN);
        }
        [$width, $height] = [(int) $info[0], (int) $info[1]];
        foreach ([$width, $height] as $side) {
            if ($side < self::MIN_SIDE || $side > self::MAX_SIDE) {
                throw new LogoRejected(LogoRejected::DIMENSIONS);
            }
        }
        return new self($bytes, $mimeType, $width, $height, hash('sha256', $bytes));
    }

    /**
     * Short content version for the address in the discovery: it changes
     * with every new picture, so the apps' caches never hold an old one.
     */
    public function version(): string
    {
        return substr($this->hash, 0, 16);
    }

    /** The media type by magic bytes, or null for anything else. */
    private static function sniff(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'image/png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'image/jpeg',
            strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'image/webp',
            default => null,
        };
    }
}
