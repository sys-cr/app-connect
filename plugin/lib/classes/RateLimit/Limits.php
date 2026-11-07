<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/** One set of fixed-window limits for {@see RateLimiter}: per client address and for the whole instance. */
final class Limits
{
    private function __construct(
        public readonly string $name,
        public readonly int $perAddress,
        public readonly int $perAddressWindow,
        public readonly int $global,
        public readonly int $globalWindow,
    ) {
    }

    /** Failed pairing attempts: 10 per address in 10 minutes, 100 per minute overall. */
    public static function pairingFailures(): self
    {
        return new self('pairing-failures', 10, 600, 100, 60);
    }

    /**
     * Revocation requests: 30 per address in 10 minutes, 300 per minute
     * overall. Every request counts, because opening a refresh token costs
     * a key derivation whether or not the token turns out to be valid.
     */
    public static function revocations(): self
    {
        return new self('revocations', 30, 600, 300, 60);
    }
}
