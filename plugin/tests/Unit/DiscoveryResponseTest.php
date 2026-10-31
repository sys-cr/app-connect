<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Discovery\DiscoveryResponse;
use PHPUnit\Framework\TestCase;

final class DiscoveryResponseTest extends TestCase
{
    public function testTheEtagChangesWithTheContent(): void
    {
        $first = new DiscoveryResponse(['payloadVersion' => 1, 'instance' => ['name' => 'A']]);
        $same = new DiscoveryResponse(['payloadVersion' => 1, 'instance' => ['name' => 'A']]);
        $other = new DiscoveryResponse(['payloadVersion' => 1, 'instance' => ['name' => 'B']]);

        self::assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $first->etag);
        self::assertSame($first->etag, $same->etag);
        self::assertNotSame($first->etag, $other->etag);
    }

    public function testIfNoneMatchDecidesAbout304(): void
    {
        $response = new DiscoveryResponse(['payloadVersion' => 1]);

        self::assertTrue($response->isNotModified($response->etag));
        self::assertTrue($response->isNotModified('"other", ' . $response->etag));
        self::assertTrue($response->isNotModified('W/' . $response->etag));
        self::assertTrue($response->isNotModified('*'));
        self::assertFalse($response->isNotModified(null));
        self::assertFalse($response->isNotModified(''));
        self::assertFalse($response->isNotModified('"other"'));
    }

    public function testTheBodyKeepsUnicodeAndSlashesReadable(): void
    {
        $response = new DiscoveryResponse(['instance' => ['name' => 'Universität', 'baseUrl' => 'https://a.example.org/x']]);

        self::assertStringContainsString('Universität', $response->body);
        self::assertStringContainsString('https://a.example.org/x', $response->body);
    }
}
