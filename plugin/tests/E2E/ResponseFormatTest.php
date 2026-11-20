<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/** Status, content type and caching of the JSON answers stay as documented (#30). */
final class ResponseFormatTest extends E2ETestCase
{
    public function testTheAnswersOfTheReadingRoutes(): void
    {
        $bearer = ['Authorization: Bearer ' . self::pair(self::AUTOR)['access_token']];

        $language = self::request('GET', self::url('jsonapi.php/v1/app-connect/user-language'), null, $bearer);
        self::assertSame(200, $language->status, $language->body);
        self::assertSame('application/json; charset=utf-8', $language->header('Content-Type'));
        self::assertSame('private, no-store', $language->header('Cache-Control'));
        self::assertArrayHasKey('data', $language->json());

        $events = self::request('GET', self::url('jsonapi.php/v1/app-connect/user-events'), null, $bearer);
        self::assertSame(200, $events->status, $events->body);
        self::assertSame('application/vnd.api+json', $events->header('Content-Type'));
        self::assertSame('private, no-store', $events->header('Cache-Control'));

        $key = self::request('GET', self::url('jsonapi.php/v1/app-connect/me/ical-key'), null, $bearer);
        self::assertSame(200, $key->status, $key->body);
        self::assertSame('application/json; charset=utf-8', $key->header('Content-Type'));
        self::assertSame('no-store', $key->header('Cache-Control'));
        self::assertStringNotContainsString('\/', $key->body, 'slashes unescaped');
    }

    public function testTheAnswersOfErrors(): void
    {
        $error = self::request('POST', self::url('jsonapi.php/v1/app-connect/devices'), '{}', [
            'Authorization: Bearer ' . self::pair(self::AUTOR)['access_token'],
            'Content-Type: application/vnd.api+json',
        ]);
        self::assertSame(422, $error->status, $error->body);
        self::assertSame('application/vnd.api+json', $error->header('Content-Type'));
        self::assertSame(
            ['errors' => [['status' => '422', 'code' => 'app-connect-invalid-device', 'title' => 'device-id, device-name and platform are required']]],
            $error->json()
        );

        $revoke = self::request('POST', self::url('jsonapi.php/v1/app-connect/revoke'), ['client_id' => 'other', 'token' => 'x']);
        self::assertSame(401, $revoke->status, $revoke->body);
        self::assertSame('application/json; charset=utf-8', $revoke->header('Content-Type'));
        self::assertSame('no-store', $revoke->header('Cache-Control'));
        self::assertSame(['error' => 'invalid_client', 'error_description' => 'Client authentication failed'], $revoke->json());
    }
}
