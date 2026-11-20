<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/**
 * "Stud.App Verbindung" for root: the logo box with its messages, the
 * legal notice in the help bar and the pairing code (#31).
 */
final class ConnectPageTest extends E2ETestCase
{
    private string $cookieJar = '';

    protected function setUp(): void
    {
        $this->cookieJar = (string) tempnam(sys_get_temp_dir(), 'app-connect-e2e');
        self::helper('config', 'APP_CONNECT_WEB_LOGIN_PRIVILEGED', 'true');
        $this->signInAsRoot();
    }

    protected function tearDown(): void
    {
        self::helper('config', 'APP_CONNECT_WEB_LOGIN_PRIVILEGED', 'false');
        @unlink($this->cookieJar);
    }

    public function testTheLogoBoxFollowsAnUploadAndItsRemoval(): void
    {
        $page = $this->page();
        self::assertStringContainsString('Logo für die App', $page->body);
        self::assertStringContainsString('app-connect wurde von Bodo Steffen', $page->body, 'the notice in the help bar');

        $uploaded = $this->upload($page->csrfToken(), __DIR__ . '/../Fixtures/logo/logo.png');
        self::assertSame(302, $uploaded->status, $uploaded->body);
        $page = $this->page();
        self::assertMatchesRegularExpression('/Hochgeladen am \d\d\.\d\d\.\d{4} \d\d:\d\d \(PNG, 64 × 48 Pixel\)/', $page->body);
        self::assertStringContainsString('messagebox_success', $page->body);

        $removed = self::request('POST', self::url('plugins.php/appconnect/connect/remove_logo'), [
            'security_token' => $page->csrfToken(),
            'remove_logo' => '',
        ], [], $this->cookieJar);
        self::assertSame(302, $removed->status, $removed->body);
        self::assertStringNotContainsString('Hochgeladen am', $this->page()->body);
    }

    public function testANewCodeCountsDownFromTheController(): void
    {
        $created = self::request('POST', self::url('plugins.php/appconnect/connect/create_code'), [
            'security_token' => $this->page()->csrfToken(),
            'create' => '',
        ], [], $this->cookieJar);

        self::assertSame(200, $created->status, $created->body);
        self::assertMatchesRegularExpression('/data-expires-in="(29\d|300)"/', $created->body);
        self::assertStringContainsString('"Wartet auf die App \u2026 noch %s"', $created->body);
    }

    private function page(): Response
    {
        $page = self::request('GET', self::url('plugins.php/appconnect/connect'), null, [], $this->cookieJar);
        self::assertSame(200, $page->status, (string) $page->header('Location'));
        return $page;
    }

    private function upload(string $token, string $file): Response
    {
        $curl = curl_init(self::url('plugins.php/appconnect/connect/upload_logo'));
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'security_token' => $token,
                'upload_logo' => '',
                'logo' => new \CURLFile($file, 'image/png', 'logo.png'),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_COOKIEJAR => $this->cookieJar,
        ]);
        $raw = (string) curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        unset($curl);
        return new Response($status, [], substr($raw, $headerSize));
    }

    /** A web session of root through the web login of the app. */
    private function signInAsRoot(): void
    {
        $link = self::request('POST', self::url('jsonapi.php/v1/app-connect/web-login'), '{}', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . self::pair(self::ROOT)['access_token'],
        ]);
        self::assertSame(201, $link->status, $link->body);
        [$enterUrl, $ticket] = explode('#', (string) $link->json()['data']['url'], 2);
        $enter = self::request('GET', $enterUrl, null, [], $this->cookieJar);
        $confirm = self::request('POST', self::url('plugins.php/appconnect/web/redeem'), [
            'security_token' => $enter->csrfToken(),
            'ticket' => $ticket,
            'redeem' => '',
        ], [], $this->cookieJar);
        $signedIn = self::request('POST', self::url('plugins.php/appconnect/web/confirm'), [
            'security_token' => $confirm->csrfToken(),
            'confirm' => '',
        ], [], $this->cookieJar);
        self::assertSame(303, $signedIn->status, $signedIn->body);
    }
}
