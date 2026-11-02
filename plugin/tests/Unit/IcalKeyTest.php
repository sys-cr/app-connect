<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Calendar\IcalKey;
use AppConnect\Calendar\IcalKeyLimit;
use AppConnect\Calendar\IcalSubscription;
use AppConnect\RateLimit\InMemoryCounterStore;
use PHPUnit\Framework\TestCase;

final class IcalKeyTest extends TestCase
{
    public function testAKeyIsThirtyTwoLowerCaseLettersAndDigits(): void
    {
        $key = IcalKey::generate();

        self::assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $key);
        // The route checks keys before handing them out, and the core's
        // controller only lets word characters through.
        self::assertTrue(IcalSubscription::isValidKey($key));
    }

    public function testKeysDoNotRepeat(): void
    {
        $keys = [];
        for ($i = 0; $i < 200; $i++) {
            $keys[IcalKey::generate()] = true;
        }

        self::assertCount(200, $keys);
    }

    public function testEveryCharacterComesFromTheGenerator(): void
    {
        $calls = [];
        $random = static function (int $min, int $max) use (&$calls): int {
            $calls[] = [$min, $max];
            return $max;
        };

        self::assertSame(str_repeat('9', IcalKey::LENGTH), IcalKey::generate($random));
        self::assertSame(array_fill(0, IcalKey::LENGTH, [0, 35]), $calls);
    }

    public function testAKeyInUseIsSkipped(): void
    {
        $values = [0, 1];
        $random = static function () use (&$values): int {
            return $values[0];
        };
        $taken = static function (string $key) use (&$values): bool {
            $values = [1];
            return $key === str_repeat('a', IcalKey::LENGTH);
        };

        self::assertSame(str_repeat('b', IcalKey::LENGTH), IcalKey::unused($taken, $random));
    }

    public function testNoKeyAfterEveryAttemptWasTaken(): void
    {
        $attempts = 0;
        $taken = static function () use (&$attempts): bool {
            $attempts++;
            return true;
        };

        self::assertNull(IcalKey::unused($taken));
        self::assertSame(IcalKey::ATTEMPTS, $attempts);
    }

    public function testFiveKeysAnHourPerPerson(): void
    {
        $limit = new IcalKeyLimit(new InMemoryCounterStore(), static fn (): int => 1_000_800);
        for ($i = 0; $i < IcalKeyLimit::MAX_CREATIONS; $i++) {
            self::assertTrue($limit->allows('user-a'));
            $limit->record('user-a');
        }

        self::assertFalse($limit->allows('user-a'));
        self::assertTrue($limit->allows('user-b'));
        self::assertSame(3600 - 1_000_800 % 3600, $limit->retryAfter());
    }

    public function testTheLimitEndsWithTheWindow(): void
    {
        $now = 1_000_800;
        $limit = new IcalKeyLimit(new InMemoryCounterStore(), static function () use (&$now): int {
            return $now;
        });
        for ($i = 0; $i < IcalKeyLimit::MAX_CREATIONS; $i++) {
            $limit->record('user-a');
        }
        self::assertFalse($limit->allows('user-a'));

        $now += $limit->retryAfter();

        self::assertTrue($limit->allows('user-a'));
    }
}
