<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\OAuth2\CoreRefreshGuard;
use PHPUnit\Framework\TestCase;

/** The app client refreshes only at the plugin's endpoint. */
final class CoreRefreshGuardTest extends TestCase
{
    private const APP = 'app-client';
    private const PATH = '/studip/dispatch.php/api/oauth2/token';

    public function testARefreshOfTheAppClientAtTheCoreEndpointIsRefused(): void
    {
        self::assertTrue(CoreRefreshGuard::refuses('POST', self::PATH, 'refresh_token', [self::APP, null], self::APP));
        self::assertTrue(CoreRefreshGuard::refuses('post', '/dispatch.php/api/oauth2/token/', 'refresh_token', [null, self::APP], self::APP));
    }

    public function testEverythingElsePasses(): void
    {
        // Other clients, other grants, other paths, other methods.
        self::assertFalse(CoreRefreshGuard::refuses('POST', self::PATH, 'refresh_token', ['other', null], self::APP));
        self::assertFalse(CoreRefreshGuard::refuses('POST', self::PATH, 'authorization_code', [self::APP, null], self::APP));
        self::assertFalse(CoreRefreshGuard::refuses('POST', '/jsonapi.php/v1/app-connect/token', 'refresh_token', [self::APP, null], self::APP));
        self::assertFalse(CoreRefreshGuard::refuses('GET', self::PATH, 'refresh_token', [self::APP, null], self::APP));
        // No app client yet.
        self::assertFalse(CoreRefreshGuard::refuses('POST', self::PATH, 'refresh_token', [self::APP, null], null));
    }

    public function testThePluginAppliesTheGuard(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../AppConnect.php');
        self::assertStringContainsString('CoreRefreshGuard::apply(', $source);
    }
}
