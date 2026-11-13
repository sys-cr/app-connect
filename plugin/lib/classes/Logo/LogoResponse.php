<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * The answer of the logo route: 200 with the picture, 304 for a known
 * entity tag, 404 without a valid copy. The picture is public and changes
 * its address with every new copy (the version in the discovery), so it
 * may be cached for a day. The type comes from the checked bytes; `nosniff`
 * and a policy without any source keep a browser from reading the answer
 * as anything but a picture.
 */
final class LogoResponse
{
    public const CACHE_CONTROL = 'public, max-age=86400';

    /** @param array<string, string> $headers */
    private function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public static function for(?LogoImage $image, ?string $ifNoneMatch): self
    {
        if ($image === null) {
            return new self(404, [], '');
        }
        $etag = '"' . $image->hash . '"';
        $headers = [
            'ETag' => $etag,
            'Cache-Control' => self::CACHE_CONTROL,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ];
        if (self::matches($etag, $ifNoneMatch)) {
            return new self(304, $headers, '');
        }
        return new self(200, $headers + [
            'Content-Type' => $image->mimeType,
            'Content-Length' => (string) strlen($image->bytes),
        ], $image->bytes);
    }

    /** Whether `If-None-Match` names $etag (weak comparison, RFC 9110 § 13.1.2). */
    private static function matches(string $etag, ?string $ifNoneMatch): bool
    {
        if ($ifNoneMatch === null || trim($ifNoneMatch) === '') {
            return false;
        }
        foreach (preg_split('/\s*,\s*/', trim($ifNoneMatch)) ?: [] as $tag) {
            if ($tag === '*' || preg_replace('~^W/~', '', $tag) === $etag) {
                return true;
            }
        }
        return false;
    }
}
