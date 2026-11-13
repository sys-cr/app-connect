<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\RateLimit\InMemoryCounterStore;
use AppConnect\RateLimit\Limits;
use AppConnect\RateLimit\RateLimiter;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private const SECRET = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testTenFailuresBlockOneAddressOnly(): void
    {
        $limits = Limits::pairingFailures();
        $limiter = new RateLimiter(new InMemoryCounterStore(), $limits, self::SECRET, static fn (): int => 1_000_000);
        for ($i = 0; $i < $limits->perAddress - 1; $i++) {
            $limiter->record('192.0.2.1');
        }
        self::assertFalse($limiter->isBlocked('192.0.2.1'));

        $limiter->record('192.0.2.1');

        self::assertSame(10, $limits->perAddress);
        self::assertTrue($limiter->isBlocked('192.0.2.1'));
        self::assertFalse($limiter->isBlocked('192.0.2.2'));
    }

    public function testTheWindowEnds(): void
    {
        $now = 1_000_200;
        $limits = Limits::pairingFailures();
        $clock = static function () use (&$now): int {
            return $now;
        };
        $limiter = new RateLimiter(new InMemoryCounterStore(), $limits, self::SECRET, $clock);
        for ($i = 0; $i < $limits->perAddress; $i++) {
            $limiter->record('192.0.2.1');
        }
        self::assertTrue($limiter->isBlocked('192.0.2.1'));

        $now += $limits->perAddressWindow;

        self::assertFalse($limiter->isBlocked('192.0.2.1'));
    }

    public function testTheGlobalLimitBlocksEveryAddress(): void
    {
        $limits = Limits::pairingFailures();
        $limiter = new RateLimiter(new InMemoryCounterStore(), $limits, self::SECRET, static fn (): int => 1_000_000);
        for ($i = 0; $i < $limits->global; $i++) {
            $limiter->record('198.51.100.' . ($i % 50));
        }

        self::assertTrue($limiter->isBlocked('203.0.113.9'));
    }

    public function testLimitSetsCountSeparately(): void
    {
        $store = new InMemoryCounterStore();
        $pairing = new RateLimiter($store, Limits::pairingFailures(), self::SECRET, static fn (): int => 1_000_000);
        $revocations = new RateLimiter($store, Limits::revocations(), self::SECRET, static fn (): int => 1_000_000);
        for ($i = 0; $i < Limits::pairingFailures()->perAddress; $i++) {
            $pairing->record('192.0.2.1');
        }

        self::assertTrue($pairing->isBlocked('192.0.2.1'));
        self::assertFalse($revocations->isBlocked('192.0.2.1'));
    }

    public function testTheCacheKeyHoldsNoAddressAndNoPlainHash(): void
    {
        $limits = Limits::pairingFailures();
        $limiter = new RateLimiter(new InMemoryCounterStore(), $limits, self::SECRET, self::zero(...));
        $key = $limiter->addressKey('192.0.2.1');

        self::assertStringNotContainsString('192.0.2.1', $key);
        self::assertStringNotContainsString(substr(hash('sha256', '192.0.2.1'), 0, 32), $key);
        self::assertMatchesRegularExpression('~^app-connect/pairing-failures/[0-9a-f]{32}/0$~', $key);
    }

    public function testAnotherSecretGivesAnotherKey(): void
    {
        $limits = Limits::pairingFailures();
        $one = new RateLimiter(new InMemoryCounterStore(), $limits, self::SECRET, self::zero(...));
        $other = new RateLimiter(new InMemoryCounterStore(), $limits, strrev(self::SECRET), self::zero(...));

        self::assertNotSame($one->addressKey('192.0.2.1'), $other->addressKey('192.0.2.1'));
    }

    public function testASecretIsRequired(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RateLimiter(new InMemoryCounterStore(), Limits::pairingFailures(), '');
    }

    private static function zero(): int
    {
        return 0;
    }

    /** Counting is one atomic increment per counter, never a read followed by a write. */
    public function testRecordingOnlyIncrements(): void
    {
        $store = new class implements \AppConnect\RateLimit\CounterStore {
            public int $reads = 0;
            /** @var list<string> */
            public array $increments = [];

            public function read(string $key): int
            {
                $this->reads++;
                return 0;
            }

            public function increment(string $key, int $ttlSeconds): int
            {
                $this->increments[] = $key;
                return 1;
            }
        };
        $limiter = new RateLimiter($store, Limits::pairingFailures(), self::SECRET, static fn (): int => 1_000_000);

        $limiter->record('192.0.2.1');

        self::assertSame(0, $store->reads);
        self::assertCount(2, $store->increments);
    }
}
