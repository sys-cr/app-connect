<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\RateLimit;

/**
 * One set of fixed-window limits for {@see RateLimiter}: per key (a client
 * address or a person) and, where it makes sense, for the whole instance.
 */
final class Limits
{
    private function __construct(
        public readonly string $name,
        public readonly int $perAddress,
        public readonly int $perAddressWindow,
        /** Null: no overall limit. */
        public readonly ?int $global,
        public readonly ?int $globalWindow,
    ) {
    }

    /**
     * Failed pairing attempts: 10 per address in 10 minutes, 1000 per minute
     * overall. A pairing code has 130 bits, so guessing is hopeless anyway;
     * the overall limit only caps the load, and set low it would let one
     * client block pairing for everyone.
     */
    public static function pairingFailures(): self
    {
        return new self('pairing-failures', 10, 600, 1000, 60);
    }

    /**
     * Revocation requests: 300 per address in 10 minutes, 1200 per minute
     * overall. Every request counts, because opening a refresh token costs
     * a key derivation whether or not the token turns out to be valid. The
     * overall limit protects the server from that cost; it is set well above
     * the sign-outs of a busy instance, so one client cannot make every
     * sign-out fail with 503.
     */
    public static function revocations(): self
    {
        return new self('revocations', 300, 600, 1200, 60);
    }

    /**
     * Failed password checks of the web login with HTTP Basic: 5 per person
     * in 15 minutes, keyed by the person, without an overall limit. Only
     * someone who already holds a session of the person gets that far.
     */
    public static function webLoginPasswords(): self
    {
        return new self('web-login-passwords', 5, 900, null, null);
    }
}
