<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Connect;

/**
 * Pairing codes: 26 characters of Crockford Base32 (130 random bits), see
 * contract/connect-link.md § 3.
 */
final class PairingCode
{
    public const LENGTH = 26;
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** Generates a new code from a cryptographically secure source. */
    public static function generate(): string
    {
        $code = '';
        $bytes = random_bytes(self::LENGTH);
        for ($i = 0; $i < self::LENGTH; $i++) {
            // 256 is a multiple of 32, so the modulo keeps the distribution uniform.
            $code .= self::ALPHABET[ord($bytes[$i]) % 32];
        }
        return $code;
    }

    /** Upper-cases a well-formed code; null for anything else. */
    public static function normalize(string $code): ?string
    {
        $upper = strtoupper($code);
        if (strlen($upper) !== self::LENGTH || strspn($upper, self::ALPHABET) !== self::LENGTH) {
            return null;
        }
        return $upper;
    }

    /** The value stored instead of the code. */
    public static function hash(string $normalizedCode): string
    {
        return hash('sha256', $normalizedCode);
    }
}
