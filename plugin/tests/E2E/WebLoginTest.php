<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/**
 * The web login from the app in a browser session (a cookie jar): the link,
 * the confirmation, the session it opens, and the refusal for root unless
 * APP_CONNECT_WEB_LOGIN_PRIVILEGED is on. With that session the profile page
 * makes a QR code whose address carries the base path, and its code
 * connects a device.
 */
final class WebLoginTest extends E2ETestCase
{
    /** An account of its own: the profile page allows ten codes per hour. */
    private const WEB = 'e2e_web';

    private string $cookieJar = '';

    protected function setUp(): void
    {
        $this->cookieJar = (string) tempnam(sys_get_temp_dir(), 'app-connect-e2e');
    }

    protected function tearDown(): void
    {
        @unlink($this->cookieJar);
        self::helper('config', 'APP_CONNECT_WEB_LOGIN_PRIVILEGED', 'false');
    }

    public function testTheLinkSignsInBelowTheBasePathAndTheQrCodeThereConnectsADevice(): void
    {
        $tokens = self::pair(self::WEB, self::BELOW);

        $confirm = $this->openLink($tokens['access_token'], self::BELOW);
        self::assertStringContainsString('anmelden?', $confirm->body, 'the confirmation "Als … anmelden?"');
        self::assertStringContainsString(self::WEB, $confirm->body);

        $signedIn = $this->post(self::url('plugins.php/appconnect/web/confirm', self::BELOW), [
            'security_token' => $confirm->csrfToken(),
            'confirm' => '',
        ]);
        self::assertSame(303, $signedIn->status, $signedIn->body);

        $profile = self::request('GET', self::url('plugins.php/appconnect/connect', self::BELOW), null, [], $this->cookieJar);
        self::assertSame(200, $profile->status, 'the session of the web login opens the profile page');
        $created = $this->post(self::url('plugins.php/appconnect/connect/create_code', self::BELOW), [
            'security_token' => $profile->csrfToken(),
            'create' => '',
        ]);
        self::assertSame(200, $created->status, 'a new code, not the redirect after too many codes');
        $landing = preg_quote(self::url('plugins.php/appconnect/get', self::BELOW), '~');
        self::assertMatchesRegularExpression('~<code class="qr hide-text">' . $landing . '#p=([0-9A-Z]{26})</code>~', $created->body);
        preg_match('~#p=([0-9A-Z]{26})</code>~', $created->body, $match);

        $redeemed = self::redeem($match[1], self::deviceId(), self::BELOW);
        self::assertSame(200, $redeemed->status, $redeemed->body);
    }

    public function testRootGetsNoWebLoginUnlessPrivilegedAccountsAreAllowed(): void
    {
        $tokens = self::pair(self::ROOT);

        $refused = $this->openLink($tokens['access_token']);
        self::assertStringContainsString('abgelaufen oder ungültig', $refused->body);
        self::assertStringNotContainsString('anmelden?', $refused->body);

        self::helper('config', 'APP_CONNECT_WEB_LOGIN_PRIVILEGED', 'true');
        $allowed = $this->openLink($tokens['access_token']);
        self::assertStringContainsString('anmelden?', $allowed->body);
    }

    public function testAWebLoginNeedsTheTokenOfTheApp(): void
    {
        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/web-login'), '{}', [
            'Content-Type: application/json',
            'Authorization: Bearer not-a-token',
        ]);

        self::assertSame(401, $response->status, $response->body);
    }

    /**
     * Asks for a link with the access token and opens it like the browser:
     * the page, then the ticket from the fragment posted to redeem.
     */
    private function openLink(string $accessToken, string $basePath = ''): Response
    {
        $link = self::request('POST', self::url('jsonapi.php/v1/app-connect/web-login', $basePath), '{}', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);
        self::assertSame(201, $link->status, $link->body);
        $url = (string) $link->json()['data']['url'];
        self::assertStringStartsWith(self::url('plugins.php/appconnect/web/enter#', $basePath), $url);
        [$page, $ticket] = explode('#', $url, 2);

        $enter = self::request('GET', $page, null, [], $this->cookieJar);
        self::assertSame(200, $enter->status);

        $redeem = $this->post(self::url('plugins.php/appconnect/web/redeem', $basePath), [
            'security_token' => $enter->csrfToken(),
            'ticket' => $ticket,
            'redeem' => '',
        ]);
        self::assertSame(200, $redeem->status, $redeem->body);
        return $redeem;
    }

    /** @param array<string, string> $form */
    private function post(string $url, array $form): Response
    {
        return self::request('POST', $url, $form, [], $this->cookieJar);
    }
}
