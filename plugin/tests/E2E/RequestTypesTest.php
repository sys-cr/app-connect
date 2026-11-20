<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Parameters of an unexpected type get the documented error, never a
 * warning or a 500 (#20).
 */
final class RequestTypesTest extends E2ETestCase
{
    public function testAClientIdListIsAnUnknownClientAtTheTokenEndpoint(): void
    {
        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/token'), 'client_id[]=' . self::clientId()
            . '&grant_type=refresh_token&refresh_token=x');

        self::assertSame(401, $response->status, $response->body);
        self::assertSame('invalid_client', $response->json()['error'] ?? null, $response->body);
    }

    public function testAClientIdListIsAnUnknownClientAtTheRevocationEndpoint(): void
    {
        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/revoke'), 'client_id[]=' . self::clientId() . '&token=x');

        self::assertSame(401, $response->status, $response->body);
        self::assertSame('invalid_client', $response->json()['error'] ?? null, $response->body);
    }

    /** @return iterable<string, array{string}> */
    public static function deviceBodies(): iterable
    {
        yield 'data is a string' => ['{"data":"x"}'];
        yield 'data is a list' => ['{"data":["x"]}'];
        yield 'attributes are a string' => ['{"data":{"type":"app-connect-devices","attributes":"x"}}'];
        yield 'device-id is a list' => ['{"data":{"type":"app-connect-devices","attributes":{"device-id":["x"],"device-name":"p","platform":"ios"}}}'];
    }

    #[DataProvider('deviceBodies')]
    public function testAnUnexpectedDeviceBodyIsAnInvalidDevice(string $body): void
    {
        $tokens = self::pair(self::AUTOR);

        $response = self::request('POST', self::url('jsonapi.php/v1/app-connect/devices'), $body, [
            'Authorization: Bearer ' . $tokens['access_token'],
            'Content-Type: application/vnd.api+json',
        ]);

        self::assertSame(422, $response->status, $response->body);
        self::assertSame('app-connect-invalid-device', $response->json()['errors'][0]['code'] ?? null, $response->body);
    }
}
