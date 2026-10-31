<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Contract;

use AppConnect\Connect\ConnectLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shared connect-link vectors (contract/test-vectors/connect-link.json), the
 * same the iOS and Android parsers run.
 */
final class ConnectLinkContractTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>}> */
    public static function vectors(): iterable
    {
        $file = dirname(__DIR__, 3) . '/contract/test-vectors/connect-link.json';
        $document = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        foreach ($document['vectors'] as $vector) {
            yield $vector['name'] => [$vector + ['reasons' => $document['reasons']]];
        }
    }

    /** @param array<string, mixed> $vector */
    #[DataProvider('vectors')]
    public function testVector(array $vector): void
    {
        $debug = $vector['build'] === 'debug';
        $result = $vector['kind'] === 'landing'
            ? ConnectLink::parseLandingUrl($vector['input'], $debug)
            : ConnectLink::parseConnectLink($vector['input'], $debug);

        self::assertSame($vector['valid'], $result->valid, 'reason: ' . ($result->reason ?? '-'));
        if ($vector['valid']) {
            self::assertSame($vector['host'], $result->host);
            self::assertSame($vector['port'] ?? null, $result->port);
            self::assertSame($vector['code'] ?? null, $result->code);
        } else {
            self::assertContains($vector['reason'], $vector['reasons']);
            self::assertSame($vector['reason'], $result->reason);
        }
    }

    public function testBuiltLinksParseBack(): void
    {
        $code = '7K2M9QX4T8WZ3RBN6FJ5HCDV0A';

        $link = ConnectLink::parseConnectLink(ConnectLink::connectLink('studip.example.org', $code), false);
        $landing = ConnectLink::parseLandingUrl(ConnectLink::landingUrl('https://studip.example.org/', $code), false);

        self::assertTrue($link->valid);
        self::assertSame($code, $link->code);
        self::assertTrue($landing->valid);
        self::assertSame('studip.example.org', $landing->host);
        self::assertSame($code, $landing->code);
    }
}
