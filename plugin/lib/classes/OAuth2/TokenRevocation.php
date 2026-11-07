<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

use AppConnect\RateLimit\RateLimiter;

/**
 * The rules of the revocation endpoint (RFC 7009, contract/token-revocation.md)
 * without the HTTP and database parts: who may ask, the rate limit, and that
 * every well-formed request of the app client gets 200, whether or not the
 * token was known.
 */
final class TokenRevocation
{
    /**
     * @param \Closure(string): ?RevocableToken $identify the pair a token names, null for an unknown token
     * @param \Closure(RevocableToken): void $revoke ends the named pair
     */
    public function __construct(
        private readonly string $clientId,
        private readonly RateLimiter $limiter,
        private readonly int $retryAfter,
        private readonly \Closure $identify,
        private readonly \Closure $revoke,
    ) {
    }

    /**
     * @param array<mixed> $params the form parameters of the request
     * @return array{status: int, error?: string, description?: string, retryAfter?: int}
     */
    public function handle(array $params, string $clientAddress): array
    {
        if ((string) ($params['client_id'] ?? '') !== $this->clientId) {
            return ['status' => 401, 'error' => 'invalid_client', 'description' => 'Client authentication failed'];
        }
        $token = $params['token'] ?? null;
        if (!is_string($token) || trim($token) === '') {
            return ['status' => 400, 'error' => 'invalid_request', 'description' => 'The token parameter is missing'];
        }
        if ($this->limiter->isBlocked($clientAddress)) {
            // RFC 7009 § 2.2.1: on 503 the client assumes the token still exists.
            return [
                'status' => 503,
                'error' => 'temporarily_unavailable',
                'description' => 'Too many requests, try again later',
                'retryAfter' => $this->retryAfter,
            ];
        }
        $this->limiter->record($clientAddress);

        $pair = ($this->identify)($token);
        if ($pair !== null) {
            ($this->revoke)($pair);
        }
        return ['status' => 200];
    }
}
