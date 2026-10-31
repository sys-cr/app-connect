<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\WebLogin\InMemoryTicketStore;
use AppConnect\WebLogin\WebLoginService;
use AppConnect\WebLogin\WebLoginTicket;
use PHPUnit\Framework\TestCase;

final class WebLoginTest extends TestCase
{
    private const USER = '0123456789abcdef0123456789abcdef';
    private const OTHER = 'fedcba9876543210fedcba9876543210';

    private InMemoryTicketStore $store;
    private int $now = 1_800_000_000;
    private WebLoginService $service;

    protected function setUp(): void
    {
        $this->store = new InMemoryTicketStore();
        $this->service = new WebLoginService($this->store, fn (): int => $this->now);
    }

    public function testTicketsCarry256RandomBitsAsBase64Url(): void
    {
        $seen = [];
        for ($i = 0; $i < 200; $i++) {
            $ticket = WebLoginTicket::generate();
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $ticket);
            self::assertTrue(WebLoginTicket::isWellFormed($ticket));
            $padded = strtr($ticket, '-_', '+/') . '=';
            self::assertSame(WebLoginTicket::BYTES, strlen((string) base64_decode($padded, true)));
            $seen[$ticket] = true;
        }
        self::assertCount(200, $seen);
        self::assertGreaterThanOrEqual(16, WebLoginTicket::BYTES);
    }

    public function testMalformedTicketsAreRecognised(): void
    {
        foreach ([null, 42, '', 'short', str_repeat('a', 42), str_repeat('a', 44), str_repeat('a', 42) . '=',
                  str_repeat('a', 42) . '+', str_repeat('a', 42) . '/', str_repeat('a', 42) . "\n"] as $value) {
            self::assertFalse(WebLoginTicket::isWellFormed($value));
        }
    }

    public function testOnlyTheHashIsStored(): void
    {
        $ticket = $this->service->issue(self::USER, 'dispatch.php/start', 'token-1', 5);

        self::assertNotNull($ticket);
        self::assertSame([hash('sha256', $ticket)], array_keys($this->store->rows));
        self::assertSame(WebLoginTicket::hash($ticket), hash('sha256', $ticket));
        $row = $this->store->rows[WebLoginTicket::hash($ticket)];
        self::assertStringNotContainsString($ticket, serialize($row));
        self::assertSame(self::USER, $row['user_id']);
        self::assertSame('dispatch.php/start', $row['return_to']);
        self::assertSame('token-1', $row['access_token_id']);
        self::assertSame(5, $row['device_row_id']);
        self::assertSame($this->now, $row['created_at']);
        self::assertSame($this->now + 60, $row['expires_at']);
        self::assertNull($row['used_at']);
    }

    public function testARedeemedTicketHandsOverItsGrantOnce(): void
    {
        $ticket = (string) $this->service->issue(self::USER, 'dispatch.php/course/details?cid=x#top', 'token-1', 5);

        $grant = $this->service->redeem($ticket);
        self::assertNotNull($grant);
        self::assertSame(self::USER, $grant->userId);
        self::assertSame('dispatch.php/course/details?cid=x#top', $grant->returnTo);
        self::assertSame('token-1', $grant->accessTokenId);
        self::assertSame(5, $grant->deviceRowId);
        self::assertSame($this->now, $this->store->rows[WebLoginTicket::hash($ticket)]['used_at']);

        self::assertNull($this->service->redeem($ticket), 'a ticket is single use');
        self::assertSame(self::USER, $this->service->ownerOf($ticket));
    }

    public function testATicketExpiresAfterSixtySeconds(): void
    {
        $ticket = (string) $this->service->issue(self::USER, null, null, null);
        $this->now += 59;
        $stillValid = (string) $this->service->issue(self::OTHER, null, null, null);
        self::assertNotNull($this->service->redeem($ticket));

        $expired = (string) $this->service->issue(self::USER, null, null, null);
        $this->now += 60;
        self::assertNull($this->service->redeem($expired));
        self::assertNull($this->store->rows[WebLoginTicket::hash($expired)]['used_at'], 'an expired ticket is not claimed');
        $this->now -= 1;
        self::assertNotNull($this->service->redeem($stillValid));
    }

    public function testOfTwoClaimsOnlyOneSucceeds(): void
    {
        $ticket = (string) $this->service->issue(self::USER, null, null, null);
        $hash = WebLoginTicket::hash($ticket);

        $first = $this->store->claim($hash, $this->now);
        $second = $this->store->claim($hash, $this->now);

        self::assertNotNull($first);
        self::assertNull($second);
    }

    public function testUnknownAndMalformedTicketsGiveNothing(): void
    {
        $this->service->issue(self::USER, null, null, null);
        self::assertNull($this->service->redeem(WebLoginTicket::generate()));
        self::assertNull($this->service->redeem('not a ticket'));
        self::assertNull($this->service->redeem(null));
        self::assertNull($this->service->ownerOf(WebLoginTicket::generate()));
        self::assertNull($this->service->ownerOf('not a ticket'));
    }

    public function testATicketWithoutDeviceOrPageStaysWithoutThem(): void
    {
        $grant = $this->service->redeem((string) $this->service->issue(self::USER, null, null, null));

        self::assertNotNull($grant);
        self::assertNull($grant->returnTo);
        self::assertNull($grant->accessTokenId);
        self::assertNull($grant->deviceRowId);
    }

    public function testAStoredPathThatNoLongerPassesIsDropped(): void
    {
        $ticket = (string) $this->service->issue(self::USER, 'dispatch.php/start', null, null);
        $this->store->rows[WebLoginTicket::hash($ticket)]['return_to'] = '//evil.example';

        self::assertNull($this->service->redeem($ticket)?->returnTo);
    }

    public function testAnUncheckedPathIsNotIssued(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->issue(self::USER, 'https://evil.example/', null, null);
    }

    public function testTenTicketsPerFiveMinutesAndPerson(): void
    {
        for ($i = 0; $i < WebLoginService::MAX_PER_WINDOW; $i++) {
            self::assertNotNull($this->service->issue(self::USER, null, null, null));
            $this->now += 10;
        }
        self::assertNull($this->service->issue(self::USER, null, null, null));
        self::assertNotNull($this->service->issue(self::OTHER, null, null, null), 'the limit is per person');

        // The first ticket was issued 100 s ago; after 300 s it no longer counts.
        $this->now += 201;
        self::assertNotNull($this->service->issue(self::USER, null, null, null));
    }

    public function testPurgeDeletesTicketsOlderThanADay(): void
    {
        $old = (string) $this->service->issue(self::USER, null, null, null);
        $this->now += 86400;
        $recent = (string) $this->service->issue(self::USER, null, null, null);
        $this->now += 1;

        self::assertSame(1, $this->service->purge());
        self::assertArrayNotHasKey(WebLoginTicket::hash($old), $this->store->rows);
        self::assertArrayHasKey(WebLoginTicket::hash($recent), $this->store->rows);
    }

    public function testForgettingAPersonRemovesTheirTickets(): void
    {
        $mine = (string) $this->service->issue(self::USER, null, null, null);
        $theirs = (string) $this->service->issue(self::OTHER, null, null, null);

        $this->store->deleteForUser(self::USER);

        self::assertNull($this->service->redeem($mine));
        self::assertNotNull($this->service->redeem($theirs));
    }
}
