<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/**
 * Pairing, refresh and revocation of the app client against a running
 * Stud.IP: the plugin's token endpoint, the core JSON:API with the token,
 * the refusal at the core token endpoint and the revocation endpoint.
 */
final class TokenLifecycleTest extends E2ETestCase
{
    public function testAPairingCodeConnectsOnceAndTheTokenReachesTheCoreApi(): void
    {
        $code = self::pairingCode(self::AUTOR);
        $deviceId = self::deviceId();

        $first = self::redeem($code, $deviceId);
        self::assertSame(200, $first->status, $first->body);
        $tokens = $first->json();
        self::assertSame('Bearer', $tokens['token_type']);
        self::assertNotEmpty($tokens['refresh_token']);

        $me = self::me((string) $tokens['access_token']);
        self::assertSame(200, $me->status, $me->body);
        self::assertSame(self::AUTOR, $me->json()['data']['attributes']['username'] ?? null);

        $device = self::device(self::AUTOR, $deviceId);
        self::assertSame('pairing', $device['paired_via']);
        self::assertNull($device['revoked_at']);

        self::assertOAuthError('invalid_grant', self::redeem($code, self::deviceId()));
    }

    public function testAnUnknownCodeIsRefused(): void
    {
        self::assertOAuthError('invalid_grant', self::redeem(str_repeat('A', 26), self::deviceId()));
    }

    public function testTheTokensOfAnotherClientAreRefusedAtThePluginEndpoint(): void
    {
        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/token'), [
            'grant_type' => self::PAIRING_GRANT,
            'client_id' => 'not-the-app',
            'code' => self::pairingCode(self::AUTOR),
            'device_id' => self::deviceId(),
            'device_name' => 'E2E phone',
            'platform' => 'ios',
        ]);

        self::assertOAuthError('invalid_client', $response);
    }

    public function testARefreshAtThePluginRotatesTheTokensAndKeepsTheDevice(): void
    {
        $tokens = self::pair(self::AUTOR);

        $refreshed = self::refresh($tokens['refresh_token']);
        self::assertSame(200, $refreshed->status, $refreshed->body);
        $next = $refreshed->json();
        self::assertNotSame($tokens['refresh_token'], $next['refresh_token']);
        self::assertSame(200, self::me((string) $next['access_token'])->status);

        self::assertRefreshRefused(self::refresh($tokens['refresh_token']));
        self::assertSame(401, self::me($tokens['access_token'])->status, 'the old access token is revoked with its pair');

        $device = self::device(self::AUTOR, $tokens['device_id']);
        self::assertNull($device['revoked_at']);
        self::assertSame(200, self::refresh((string) $next['refresh_token'])->status, 'the device follows the lineage');
    }

    public function testTheCoreTokenEndpointRefusesARefreshOfTheAppClient(): void
    {
        $tokens = self::pair(self::AUTOR);

        $response = self::refresh($tokens['refresh_token'], 'dispatch.php/api/oauth2/token');

        self::assertSame(400, $response->status, $response->body);
        self::assertSame('invalid_grant', $response->json()['error'] ?? null);
        self::assertSame(200, self::refresh($tokens['refresh_token'])->status, 'the refresh token stays usable at the plugin');
    }

    public function testRevokingTheRefreshTokenEndsThePairAndTheDevice(): void
    {
        $tokens = self::pair(self::AUTOR);

        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/revoke'), [
            'token' => $tokens['refresh_token'],
            'client_id' => self::clientId(),
        ]);

        self::assertSame(200, $response->status, $response->body);
        self::assertSame(401, self::me($tokens['access_token'])->status);
        self::assertRefreshRefused(self::refresh($tokens['refresh_token']));
        self::assertNotNull(self::device(self::AUTOR, $tokens['device_id'])['revoked_at']);
    }

    public function testRevokingTheAccessTokenEndsThePairAndTheDevice(): void
    {
        $tokens = self::pair(self::AUTOR);

        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/revoke'), [
            'token' => $tokens['access_token'],
            'client_id' => self::clientId(),
        ]);

        self::assertSame(200, $response->status, $response->body);
        self::assertRefreshRefused(self::refresh($tokens['refresh_token']));
        self::assertNotNull(self::device(self::AUTOR, $tokens['device_id'])['revoked_at']);
    }

    public function testRevokingAnUnknownTokenAnswers200(): void
    {
        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/revoke'), [
            'token' => 'unknown',
            'client_id' => self::clientId(),
        ]);

        self::assertSame(200, $response->status, $response->body);
    }

    public function testTheWholeFlowWorksBelowTheBasePath(): void
    {
        $tokens = self::pair(self::AUTOR, self::BELOW);

        self::assertSame(200, self::me($tokens['access_token'], self::BELOW)->status);
        $refreshed = self::request('POST', self::url('jsonapi.php/v1/app-connect/token', self::BELOW), [
            'grant_type' => 'refresh_token',
            'client_id' => self::clientId(),
            'refresh_token' => $tokens['refresh_token'],
        ]);
        self::assertSame(200, $refreshed->status, $refreshed->body);
    }
}
