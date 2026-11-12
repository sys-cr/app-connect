<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Logo\HttpReply;
use AppConnect\Logo\InMemoryLogoStore;
use AppConnect\Logo\LogoFetch;
use AppConnect\Logo\LogoRejected;
use AppConnect\Logo\LogoService;
use AppConnect\Logo\LogoStatusText;
use AppConnect\Tests\Support\FakeHttpFetcher;
use PHPUnit\Framework\TestCase;

final class LogoServiceTest extends TestCase
{
    private const URL = 'https://www.example.org/logo.png';

    private FakeHttpFetcher $http;
    private InMemoryLogoStore $store;
    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        $this->http = new FakeHttpFetcher();
        $this->store = new InMemoryLogoStore();
    }

    private function service(): LogoService
    {
        return new LogoService(
            $this->store,
            new LogoFetch($this->http, static fn (string $host): array => ['93.184.215.14'], 'studip.example.org'),
            fn (): int => $this->now
        );
    }

    public function testAFetchedLogoIsStoredForItsAddress(): void
    {
        $this->http->answer(self::URL, new HttpReply(200, FakeHttpFetcher::fixture('logo.webp')));

        $logo = $this->service()->refresh(' ' . self::URL . ' ');

        self::assertNotNull($logo);
        self::assertSame(self::URL, $logo->sourceUrl);
        self::assertSame('image/webp', $logo->image?->mimeType);
        self::assertSame($this->now, $logo->fetchedAt);
        self::assertNull($logo->problem);
        self::assertSame($logo->image, $this->service()->current(self::URL));
        self::assertSame(['success', 'Das Logo wurde geladen. Die Apps erhalten es von diesem Server.'], LogoStatusText::outcome($logo));
    }

    public function testAFailedRefreshKeepsTheLastGoodCopyAndRecordsWhy(): void
    {
        $this->http->answer(self::URL, new HttpReply(200, FakeHttpFetcher::fixture('logo.png')));
        $first = $this->service()->refresh(self::URL);
        $fetchedAt = $this->now;

        $this->now += 86400;
        $this->http->answer(self::URL, new HttpReply(200, FakeHttpFetcher::fixture('logo.svg')));
        $second = $this->service()->refresh(self::URL);

        self::assertSame($first?->image, $second?->image);
        self::assertSame($fetchedAt, $second?->fetchedAt);
        self::assertSame($this->now, $second?->checkedAt);
        self::assertSame(LogoRejected::TYPE, $second?->problem);
        self::assertSame($first?->image, $this->service()->current(self::URL));
        self::assertSame('warning', LogoStatusText::outcome($second)[0] ?? null);

        // The next good fetch clears the problem.
        $this->http->answer(self::URL, new HttpReply(200, FakeHttpFetcher::fixture('logo.jpg')));
        $third = $this->service()->refresh(self::URL);
        self::assertNull($third?->problem);
        self::assertSame('image/jpeg', $this->service()->current(self::URL)?->mimeType);
    }

    public function testANewAddressThatFailsLeavesNoCopy(): void
    {
        $this->http->answer(self::URL, new HttpReply(200, FakeHttpFetcher::fixture('logo.png')));
        $this->service()->refresh(self::URL);

        $other = 'https://www.example.org/new-logo.png';
        $logo = $this->service()->refresh($other);

        self::assertNull($logo?->image);
        self::assertSame(LogoRejected::UNREACHABLE, $logo?->problem);
        self::assertNull($this->service()->current($other));
        self::assertNull($this->service()->current(self::URL));
        self::assertSame('error', LogoStatusText::outcome($logo)[0] ?? null);
    }

    public function testACopyIsOnlyServedForTheConfiguredAddress(): void
    {
        $this->http->answer(self::URL, new HttpReply(200, FakeHttpFetcher::fixture('logo.png')));
        $this->service()->refresh(self::URL);

        self::assertNull($this->service()->current('https://www.example.org/other.png'));
        self::assertNull($this->service()->current(null));
        self::assertNull($this->service()->status(null));
    }

    public function testRemovingTheAddressDeletesTheCopy(): void
    {
        $this->http->answer(self::URL, new HttpReply(200, FakeHttpFetcher::fixture('logo.png')));
        $this->service()->refresh(self::URL);

        self::assertNull($this->service()->refresh(null));
        self::assertNull($this->store->load());

        $this->service()->refresh(self::URL);
        self::assertNull($this->service()->refresh('http://www.example.org/logo.png'));
        self::assertNull($this->store->load());
        self::assertNull(LogoStatusText::outcome(null));
    }

    public function testEveryReasonHasAText(): void
    {
        $texts = array_map([LogoStatusText::class, 'reason'], LogoRejected::REASONS);

        self::assertCount(count(LogoRejected::REASONS), array_unique($texts));
        foreach ($texts as $text) {
            self::assertStringNotContainsString('—', $text);
        }
    }
}
