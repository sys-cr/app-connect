<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

/**
 * Web-login tickets: 32 random bytes (256 bits) as base64url without
 * padding, 43 characters. Only the SHA-256 hash is stored, as with the
 * pairing codes.
 */
final class WebLoginTicket
{
    public const BYTES = 32;
    public const LENGTH = 43;

    /** A new ticket from a cryptographically secure source. */
    public static function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::BYTES)), '+/', '-_'), '=');
    }

    public static function isWellFormed(mixed $ticket): bool
    {
        return is_string($ticket) && preg_match('/^[A-Za-z0-9_-]{' . self::LENGTH . '}$/', $ticket) === 1;
    }

    /** The value stored instead of the ticket. */
    public static function hash(string $ticket): string
    {
        return hash('sha256', $ticket);
    }
}
