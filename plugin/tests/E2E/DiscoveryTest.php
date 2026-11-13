<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

use AppConnect\Tests\Contract\DiscoverySchema;
use PHPUnit\Framework\Attributes\DataProvider;

/** The public discovery and the landing page, at the root and below /studip. */
final class DiscoveryTest extends E2ETestCase
{
    /** @return array<string, array{string}> */
    public static function basePaths(): array
    {
        return ['root' => [''], 'below /studip' => [self::BELOW]];
    }

    #[DataProvider('basePaths')]
    public function testTheDiscoveryNamesTheEndpointsWithTheBasePath(string $basePath): void
    {
        $discovery = self::discovery($basePath);
        $base = self::url('', $basePath);
        $base = rtrim($base, '/');

        DiscoverySchema::assertValid($discovery);
        self::assertSame($base, $discovery['instance']['baseUrl']);
        self::assertSame($base . '/jsonapi.php/v1', $discovery['instance']['jsonApiUrl']);
        self::assertSame($base . '/jsonapi.php/v1/app-connect/token', $discovery['oauth2']['tokenEndpoint']);
        self::assertSame($base . '/jsonapi.php/v1/app-connect/revoke', $discovery['oauth2']['revocationEndpoint']);
        self::assertSame($base . '/dispatch.php/api/oauth2/authorize', $discovery['oauth2']['authorizationEndpoint']);
    }

    public function testTheDiscoveryAnswersAnUnchangedETagWith304(): void
    {
        $first = self::request('GET', self::url('jsonapi.php/v1/app-connect/discovery'));
        $etag = (string) $first->header('ETag');
        self::assertNotSame('', $etag);

        $again = self::request('GET', self::url('jsonapi.php/v1/app-connect/discovery'), null, ['If-None-Match: ' . $etag]);

        self::assertSame(304, $again->status);
    }

    public function testTheLandingPageAtTheRootHasNoPathInTheConnectLink(): void
    {
        $page = self::request('GET', self::url('plugins.php/appconnect/get'));

        self::assertSame(200, $page->status);
        self::assertStringContainsString('data-connect-link="de.studapp2.open:/connect?host=' . self::host() . '"', $page->body);
    }

    public function testTheLandingPageBelowStudipCarriesTheBasePath(): void
    {
        $page = self::request('GET', self::url('plugins.php/appconnect/get', self::BELOW));

        self::assertSame(200, $page->status);
        self::assertStringContainsString(
            'data-connect-link="de.studapp2.open:/connect?host=' . self::host() . '&amp;path=%2Fstudip"',
            $page->body
        );
    }

    /** host[:port] of the instance as the connect link encodes it. */
    private static function host(): string
    {
        $parts = parse_url(self::url(''));
        return rawurlencode($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    }
}
