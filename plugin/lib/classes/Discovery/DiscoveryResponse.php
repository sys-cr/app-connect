<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Discovery;

/**
 * Serialised discovery payload with its entity tag.
 */
final class DiscoveryResponse
{
    public const CACHE_CONTROL = 'public, max-age=300';

    public readonly string $body;
    public readonly string $etag;

    /** @param array<string, mixed> $payload */
    public function __construct(array $payload)
    {
        $this->body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->etag = '"' . hash('sha256', $this->body) . '"';
    }

    /**
     * Whether an `If-None-Match` header names this response, so a 304 can be
     * sent. Weak validators match too (RFC 9110 § 13.1.2 uses weak comparison).
     */
    public function isNotModified(?string $ifNoneMatch): bool
    {
        if ($ifNoneMatch === null || trim($ifNoneMatch) === '') {
            return false;
        }
        foreach (preg_split('/\s*,\s*/', trim($ifNoneMatch)) ?: [] as $tag) {
            if ($tag === '*' || preg_replace('~^W/~', '', $tag) === $this->etag) {
                return true;
            }
        }
        return false;
    }
}
