<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\RateLimit\InMemoryCounterStore;
use AppConnect\RateLimit\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testTenFailuresBlockOneAddressOnly(): void
    {
        $limiter = new RateLimiter(new InMemoryCounterStore(), static fn (): int => 1_000_000);
        for ($i = 0; $i < RateLimiter::PER_ADDRESS - 1; $i++) {
            $limiter->recordFailure('192.0.2.1');
        }
        self::assertFalse($limiter->isBlocked('192.0.2.1'));

        $limiter->recordFailure('192.0.2.1');

        self::assertTrue($limiter->isBlocked('192.0.2.1'));
        self::assertFalse($limiter->isBlocked('192.0.2.2'));
    }

    public function testTheWindowEnds(): void
    {
        $now = 1_000_200;
        $limiter = new RateLimiter(new InMemoryCounterStore(), static function () use (&$now): int {
            return $now;
        });
        for ($i = 0; $i < RateLimiter::PER_ADDRESS; $i++) {
            $limiter->recordFailure('192.0.2.1');
        }
        self::assertTrue($limiter->isBlocked('192.0.2.1'));

        $now += RateLimiter::PER_ADDRESS_WINDOW;

        self::assertFalse($limiter->isBlocked('192.0.2.1'));
    }

    public function testTheGlobalLimitBlocksEveryAddress(): void
    {
        $limiter = new RateLimiter(new InMemoryCounterStore(), static fn (): int => 1_000_000);
        for ($i = 0; $i < RateLimiter::GLOBAL; $i++) {
            $limiter->recordFailure('198.51.100.' . ($i % 50));
        }

        self::assertTrue($limiter->isBlocked('203.0.113.9'));
    }
}
