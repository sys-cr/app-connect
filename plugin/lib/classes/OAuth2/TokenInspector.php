<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

/**
 * Tells which token of the app client a revocation request names (RFC 7009).
 *
 * Access tokens are JWTs signed by the core with RS256; the signature is
 * checked against the core's public key, the expiry is not, so an expired
 * access token still ends its refresh token. Refresh tokens are encrypted
 * by the core (league/oauth2-server, defuse/php-encryption); a token that
 * does not decrypt is unknown. Tokens of other clients count as unknown as
 * well, so the answer never tells whether a token exists.
 *
 * The kind is told apart by the token's form, so the `token_type_hint` of
 * the request is not needed; a wrong hint changes nothing.
 */
final class TokenInspector
{
    /** Longer values are no token of the core. */
    public const MAX_LENGTH = 8192;

    private const JWT = '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/';
    private const ENCRYPTED = '/^[0-9a-f]+$/';

    /**
     * @param string $publicKey PEM of the core's public key for access tokens
     * @param \Closure(string): ?string $openRefreshToken plaintext of an encrypted refresh token,
     *     null when it does not decrypt
     */
    public function __construct(
        private readonly string $clientId,
        private readonly string $publicKey,
        private readonly \Closure $openRefreshToken,
    ) {
    }

    public function identify(string $token): ?RevocableToken
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > self::MAX_LENGTH) {
            return null;
        }
        if (preg_match(self::JWT, $token) === 1) {
            return $this->accessToken($token);
        }
        if (preg_match(self::ENCRYPTED, $token) === 1) {
            return $this->refreshToken($token);
        }
        return null;
    }

    private function accessToken(string $jwt): ?RevocableToken
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $headerData = self::decodeJson(self::base64UrlDecode($header));
        if (($headerData['alg'] ?? null) !== 'RS256') {
            return null;
        }
        $rawSignature = self::base64UrlDecode($signature);
        $key = openssl_pkey_get_public($this->publicKey);
        if ($rawSignature === null || $key === false
            || openssl_verify($header . '.' . $payload, $rawSignature, $key, OPENSSL_ALGO_SHA256) !== 1) {
            return null;
        }
        $claims = self::decodeJson(self::base64UrlDecode($payload));
        $id = $claims['jti'] ?? null;
        if (!is_string($id) || $id === '' || !$this->isAudience($claims['aud'] ?? null)) {
            return null;
        }
        return new RevocableToken($id, null);
    }

    private function refreshToken(string $encrypted): ?RevocableToken
    {
        $plain = ($this->openRefreshToken)($encrypted);
        $data = is_string($plain) ? self::decodeJson($plain) : null;
        if ($data === null || (string) ($data['client_id'] ?? '') !== $this->clientId) {
            return null;
        }
        $accessTokenId = $data['access_token_id'] ?? null;
        $refreshTokenId = $data['refresh_token_id'] ?? null;
        if (!is_string($accessTokenId) || $accessTokenId === ''
            || !is_string($refreshTokenId) || $refreshTokenId === '') {
            return null;
        }
        return new RevocableToken($accessTokenId, $refreshTokenId);
    }

    /** The core writes a single audience as a string, some versions as a list of one. */
    private function isAudience(mixed $audience): bool
    {
        if (is_array($audience)) {
            return count($audience) === 1 && (string) reset($audience) === $this->clientId;
        }
        return is_scalar($audience) && (string) $audience === $this->clientId;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }

    /** @return array<string, mixed>|null */
    private static function decodeJson(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }
        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return null;
        }
        return is_array($data) && !array_is_list($data) ? $data : null;
    }
}
