<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\OAuth2\TokenInspector;
use PHPUnit\Framework\TestCase;

/**
 * Which token a revocation request names: signed access tokens and encrypted
 * refresh tokens of the app client are recognised, everything else is unknown.
 */
final class TokenInspectorTest extends TestCase
{
    private const CLIENT = '7';
    private const REFRESH = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private static \OpenSSLAsymmetricKey $key;
    private static string $publicKey;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        self::$key = $key;
        self::$publicKey = (string) openssl_pkey_get_details($key)['key'];
    }

    public function testASignedAccessTokenOfTheAppNamesItsId(): void
    {
        $token = $this->inspector()->identify(self::jwt(['jti' => 'abc123', 'aud' => self::CLIENT, 'sub' => 'u1']));

        self::assertNotNull($token);
        self::assertSame('abc123', $token->accessTokenId);
        self::assertNull($token->refreshTokenId);
    }

    public function testAnAudienceListOfOneIsAccepted(): void
    {
        $token = $this->inspector()->identify(self::jwt(['jti' => 'abc123', 'aud' => [self::CLIENT]]));

        self::assertSame('abc123', $token?->accessTokenId);
    }

    public function testAnExpiredAccessTokenIsStillRecognised(): void
    {
        $token = $this->inspector()->identify(self::jwt(['jti' => 'old', 'aud' => self::CLIENT, 'exp' => 1]));

        self::assertSame('old', $token?->accessTokenId);
    }

    public function testAnAccessTokenOfAnotherClientIsUnknown(): void
    {
        self::assertNull($this->inspector()->identify(self::jwt(['jti' => 'abc123', 'aud' => '8'])));
        self::assertNull($this->inspector()->identify(self::jwt(['jti' => 'abc123', 'aud' => [self::CLIENT, '8']])));
        self::assertNull($this->inspector()->identify(self::jwt(['jti' => 'abc123'])));
    }

    public function testATamperedOrForgedAccessTokenIsUnknown(): void
    {
        $jwt = self::jwt(['jti' => 'abc123', 'aud' => self::CLIENT]);
        [$header, , $signature] = explode('.', $jwt);
        $otherPayload = self::base64Url((string) json_encode(['jti' => 'victim', 'aud' => self::CLIENT]));

        self::assertNull($this->inspector()->identify($header . '.' . $otherPayload . '.' . $signature));

        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($other);
        self::assertNull($this->inspector()->identify(self::jwt(['jti' => 'abc123', 'aud' => self::CLIENT], $other)));
    }

    public function testAnUnsignedAccessTokenIsUnknown(): void
    {
        $header = self::base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'none']));
        $payload = self::base64Url((string) json_encode(['jti' => 'abc123', 'aud' => self::CLIENT]));

        self::assertNull($this->inspector()->identify($header . '.' . $payload . '.x'));
    }

    public function testARefreshTokenOfTheAppNamesItsPair(): void
    {
        $token = $this->inspector()->identify(self::REFRESH);

        self::assertNotNull($token);
        self::assertSame('at1', $token->accessTokenId);
        self::assertSame('rt1', $token->refreshTokenId);
    }

    public function testARefreshTokenOfAnotherClientIsUnknown(): void
    {
        $inspector = $this->inspector(['client_id' => '8', 'access_token_id' => 'at1', 'refresh_token_id' => 'rt1']);

        self::assertNull($inspector->identify(self::REFRESH));
    }

    public function testARefreshTokenThatDoesNotDecryptIsUnknown(): void
    {
        self::assertNull($this->inspector()->identify('00ff00ff'));
    }

    public function testAnythingElseIsUnknownWithoutDecrypting(): void
    {
        $opened = 0;
        $inspector = new TokenInspector(self::CLIENT, self::$publicKey, static function () use (&$opened): ?string {
            $opened++;
            return null;
        });

        $values = ['', '   ', 'not a token', 'ABCDEF', str_repeat('a', TokenInspector::MAX_LENGTH + 1), 'a.b'];
        foreach ($values as $value) {
            self::assertNull($inspector->identify($value));
        }
        self::assertSame(0, $opened);
    }

    /** @param array<string, mixed> $refreshPayload */
    private function inspector(array $refreshPayload = [
        'client_id' => self::CLIENT,
        'refresh_token_id' => 'rt1',
        'access_token_id' => 'at1',
        'user_id' => 'u1',
        'expire_time' => 1,
    ]): TokenInspector {
        return new TokenInspector(
            self::CLIENT,
            self::$publicKey,
            static fn (string $token): ?string
                => $token === self::REFRESH ? (string) json_encode($refreshPayload) : null
        );
    }

    /** @param array<string, mixed> $claims */
    private static function jwt(array $claims, ?\OpenSSLAsymmetricKey $key = null): string
    {
        $input = self::base64Url((string) json_encode(['typ' => 'JWT', 'alg' => 'RS256']))
            . '.' . self::base64Url((string) json_encode($claims));
        openssl_sign($input, $signature, $key ?? self::$key, OPENSSL_ALGO_SHA256);
        return $input . '.' . self::base64Url($signature);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
