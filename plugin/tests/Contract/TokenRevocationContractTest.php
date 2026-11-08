<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Contract;

use AppConnect\OAuth2\RevocableToken;
use AppConnect\OAuth2\TokenRevocation;
use AppConnect\RateLimit\InMemoryCounterStore;
use AppConnect\RateLimit\Limits;
use AppConnect\RateLimit\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shared revocation vectors (contract/test-vectors/token-revocation.json)
 * against the rules of the endpoint. Which token is which comes from a fixed
 * table here; recognising real tokens is covered by TokenInspectorTest.
 */
final class TokenRevocationContractTest extends TestCase
{
    private const ADDRESS = '192.0.2.1';
    private const SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /** Placeholder tokens of the vectors that the app client issued. */
    private const KNOWN = [
        '<refresh token>' => ['at1', 'rt1'],
        '<access token>' => ['at1', null],
        '<expired access token>' => ['at0', null],
        '<revoked refresh token>' => ['at0', 'rt0'],
    ];

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function vectors(): iterable
    {
        $document = self::document();
        foreach ($document['cases'] as $case) {
            yield $case['name'] => [$case, (string) $document['clientId']];
        }
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('vectors')]
    public function testVector(array $case, string $clientId): void
    {
        $limits = Limits::revocations();
        $limiter = new RateLimiter(new InMemoryCounterStore(), $limits, self::SECRET, static fn (): int => 1_000_000);
        if ($case['status'] === 503) {
            for ($i = 0; $i < $limits->perAddress; $i++) {
                $limiter->record(self::ADDRESS);
            }
        }
        $revoked = [];
        $revocation = new TokenRevocation(
            $clientId,
            $limiter,
            $limits->perAddressWindow,
            static function (string $token): ?RevocableToken {
                $known = self::KNOWN[$token] ?? null;
                return $known === null ? null : new RevocableToken($known[0], $known[1]);
            },
            static function (RevocableToken $pair) use (&$revoked): void {
                $revoked[] = $pair;
            }
        );
        parse_str($case['body'], $params);

        $result = $revocation->handle($params, self::ADDRESS);

        self::assertSame($case['status'], $result['status']);
        self::assertSame($case['error'] ?? null, $result['error'] ?? null);
        self::assertSame($case['retryAfter'] ?? null, $result['retryAfter'] ?? null);
        self::assertCount($case['revokes'] === 'pair' ? 1 : 0, $revoked);
    }

    public function testTheVectorsNameThePluginEndpoint(): void
    {
        $document = self::document();

        self::assertSame('https://studip.example.org/jsonapi.php/v1/app-connect/revoke', $document['endpoint']);
        $file = dirname(__DIR__, 3) . '/contract/test-vectors/discovery/revocation-endpoint.json';
        $discovery = json_decode(
            (string) file_get_contents($file),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        self::assertSame($document['endpoint'], $discovery['oauth2']['revocationEndpoint']);
        self::assertSame($document['clientId'], $discovery['oauth2']['clientId']);
    }

    public function testEveryRequestCountsTowardsTheLimit(): void
    {
        $limits = Limits::revocations();
        $now = 1_000_000;
        $limiter = new RateLimiter(new InMemoryCounterStore(), $limits, self::SECRET, function () use (&$now): int {
            return $now;
        });
        $revocation = new TokenRevocation(
            '7',
            $limiter,
            600,
            static fn (): ?RevocableToken => null,
            static function (): void {
            }
        );
        for ($i = 0; $i < $limits->perAddress; $i++) {
            $result = $revocation->handle(['client_id' => '7', 'token' => 'x' . $i], self::ADDRESS);
            self::assertSame(200, $result['status']);
        }

        self::assertSame(503, $revocation->handle(['client_id' => '7', 'token' => 'x'], self::ADDRESS)['status']);

        // Once the overall window has passed, the address stays blocked for the
        // rest of its own window while another address gets through.
        $now += $limits->globalWindow;
        self::assertSame(503, $revocation->handle(['client_id' => '7', 'token' => 'x'], self::ADDRESS)['status']);
        self::assertSame(200, $revocation->handle(['client_id' => '7', 'token' => 'x'], '192.0.2.2')['status']);
    }

    /** @return array<string, mixed> */
    private static function document(): array
    {
        $file = dirname(__DIR__, 3) . '/contract/test-vectors/token-revocation.json';
        return json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    }
}
