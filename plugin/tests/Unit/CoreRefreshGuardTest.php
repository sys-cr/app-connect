<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\OAuth2\CoreRefreshGuard;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** @return iterable<string, array{array<string, string>, array<string, string>}> */
    public static function refreshesOfTheAppClient(): iterable
    {
        $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => self::PATH . '?x=1'];
        $basic = 'Basic ' . base64_encode(self::APP . ':');
        yield 'client_id in the body' => [$server, ['grant_type' => 'refresh_token', 'client_id' => self::APP]];
        yield 'PHP_AUTH_USER (mod_php)' => [$server + ['PHP_AUTH_USER' => self::APP], ['grant_type' => 'refresh_token']];
        yield 'HTTP_AUTHORIZATION (FastCGI)' => [$server + ['HTTP_AUTHORIZATION' => $basic], ['grant_type' => 'refresh_token']];
        yield 'REDIRECT_HTTP_AUTHORIZATION (rewrite)' => [$server + ['REDIRECT_HTTP_AUTHORIZATION' => $basic], ['grant_type' => 'refresh_token']];
        yield 'with a secret' => [$server + ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode(self::APP . ':s:e')], ['grant_type' => 'refresh_token']];
        yield 'lower-case scheme' => [$server + ['HTTP_AUTHORIZATION' => 'basic ' . base64_encode(self::APP . ':')], ['grant_type' => 'refresh_token']];
        yield 'another client in the body' => [$server + ['HTTP_AUTHORIZATION' => $basic], ['grant_type' => 'refresh_token', 'client_id' => 'other']];
    }

    /**
     * @param array<string, string> $server
     * @param array<string, string> $post
     */
    #[DataProvider('refreshesOfTheAppClient')]
    public function testTheCheckFindsTheAppClientWhereverLeagueFindsIt(array $server, array $post): void
    {
        self::assertTrue(CoreRefreshGuard::check($server, $post, static fn (): string => self::APP));
    }

    public function testTheCheckLetsOtherRequestsThrough(): void
    {
        $server = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => self::PATH];
        $app = static fn (): string => self::APP;
        $other = 'Basic ' . base64_encode('other:');

        self::assertFalse(CoreRefreshGuard::check($server + ['HTTP_AUTHORIZATION' => $other], ['grant_type' => 'refresh_token'], $app));
        self::assertFalse(CoreRefreshGuard::check($server + ['HTTP_AUTHORIZATION' => 'Bearer x'], ['grant_type' => 'refresh_token'], $app));
        self::assertFalse(CoreRefreshGuard::check($server, ['grant_type' => 'authorization_code', 'client_id' => self::APP], $app));
        self::assertFalse(CoreRefreshGuard::check(['REQUEST_URI' => '/jsonapi.php/v1/app-connect/token'] + $server, ['grant_type' => 'refresh_token', 'client_id' => self::APP], $app));
        self::assertFalse(CoreRefreshGuard::check($server, ['grant_type' => ['refresh_token'], 'client_id' => self::APP], $app));
        self::assertFalse(CoreRefreshGuard::check($server, ['grant_type' => 'refresh_token', 'client_id' => self::APP], static fn (): ?string => null));
    }

    public function testTheClientIdIsLookedUpOnlyForARefreshAtTheCorePath(): void
    {
        $lookups = 0;
        $count = static function () use (&$lookups): string {
            $lookups++;
            return self::APP;
        };

        CoreRefreshGuard::check(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/dispatch.php/start'], [], $count);
        CoreRefreshGuard::check(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => self::PATH], ['grant_type' => 'authorization_code'], $count);

        self::assertSame(0, $lookups);
    }

    public function testBasicUserSplitsTheHeaderLikeLeague(): void
    {
        self::assertSame('a', CoreRefreshGuard::basicUser('Basic ' . base64_encode('a:')));
        self::assertSame('a', CoreRefreshGuard::basicUser('Basic ' . base64_encode('a:b:c')));
        self::assertSame('', CoreRefreshGuard::basicUser('Basic ' . base64_encode(':b')));
        self::assertNull(CoreRefreshGuard::basicUser('Basic ' . base64_encode('a')));
        self::assertNull(CoreRefreshGuard::basicUser('Basic %%%'));
        self::assertNull(CoreRefreshGuard::basicUser('Bearer ' . base64_encode('a:')));
        self::assertNull(CoreRefreshGuard::basicUser(null));
        self::assertNull(CoreRefreshGuard::basicUser(['Basic x']));
    }

    public function testThePluginAppliesTheGuard(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../AppConnect.php');
        self::assertStringContainsString('CoreRefreshGuard::apply(', $source);
    }
}
