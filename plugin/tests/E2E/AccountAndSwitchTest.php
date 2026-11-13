<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/**
 * What ends a connection from the server side: a locked account
 * (AccountGate) and switching the app connection off in the configuration.
 */
final class AccountAndSwitchTest extends E2ETestCase
{
    private const LOCKED = 'e2e_locked';

    protected function tearDown(): void
    {
        self::helper('lock', self::LOCKED, '0');
        if (!self::helper('state')['enabled']) {
            self::helper('config', 'APP_CONNECT_ENABLED', 'true');
        }
    }

    public function testALockedAccountLosesTheDeviceAtTheNextRefresh(): void
    {
        $tokens = self::pair(self::LOCKED);

        self::helper('lock', self::LOCKED, '1');

        self::assertRefreshRefused(self::refresh($tokens['refresh_token']));
        self::assertNotNull(self::device(self::LOCKED, $tokens['device_id'])['revoked_at']);
        self::helper('lock', self::LOCKED, '0');
        self::assertRefreshRefused(self::refresh($tokens['refresh_token']));
    }

    public function testALockedAccountCannotPair(): void
    {
        self::helper('lock', self::LOCKED, '1');

        self::assertOAuthError('invalid_grant', self::redeem(self::pairingCode(self::LOCKED), self::deviceId()));
    }

    public function testSwitchingOffEndsEveryConnectionForGood(): void
    {
        $tokens = self::pair(self::AUTOR);
        self::assertSame(200, self::me($tokens['access_token'])->status);

        self::helper('config', 'APP_CONNECT_ENABLED', 'false');

        self::assertFalse(self::helper('state')['enabled']);
        $discovery = self::request('GET', self::url('jsonapi.php/v1/app-connect/discovery'));
        self::assertSame(404, $discovery->status);
        self::assertSame('app-connect-disabled', $discovery->json()['errors'][0]['code'] ?? null);
        self::assertSame(401, self::me($tokens['access_token'])->status);
        self::assertNotNull(self::device(self::AUTOR, $tokens['device_id'])['revoked_at']);

        // Switching on again reuses the client but brings no old token back.
        $clientId = self::helper('state')['clientId'];
        self::helper('config', 'APP_CONNECT_ENABLED', 'true');
        self::assertSame($clientId, self::clientId());
        self::assertSame(401, self::me($tokens['access_token'])->status);
        self::assertRefreshRefused(self::refresh($tokens['refresh_token']));
        self::assertSame(200, self::me(self::pair(self::AUTOR)['access_token'])->status, 'new connections work');
    }

    public function testABasePathOutsideTheRulesKeepsTheAppConnectionOff(): void
    {
        self::helper('config', 'APP_CONNECT_ENABLED', 'false');

        $state = self::helper('enable-at', self::url('stud%20ip/'));

        self::assertFalse($state['enabled']);
        self::assertSame(404, self::request('GET', self::url('jsonapi.php/v1/app-connect/discovery'))->status);
    }
}
