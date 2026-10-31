<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Connect\PairingCode;
use PHPUnit\Framework\TestCase;

final class PairingCodeTest extends TestCase
{
    public function testGeneratedCodesUseTheAlphabetAndDoNotRepeat(): void
    {
        $codes = [];
        for ($i = 0; $i < 200; $i++) {
            $code = PairingCode::generate();
            self::assertSame($code, PairingCode::normalize($code));
            $codes[$code] = true;
        }
        self::assertCount(200, $codes);
    }

    public function testNormalizeAcceptsLowerCaseOnly(): void
    {
        self::assertSame('7K2M9QX4T8WZ3RBN6FJ5HCDV0A', PairingCode::normalize('7k2m9qx4t8wz3rbn6fj5hcdv0a'));
        self::assertNull(PairingCode::normalize('7K2M9QX4T8WZ3RBN6FJ5HCDV0'));
        self::assertNull(PairingCode::normalize('OK2M9QX4T8WZ3RBN6FJ5HCDV0A'));
        self::assertNull(PairingCode::normalize('7K2M9QX4T8WZ3RBN6FJ5HCDV0 '));
    }

    public function testTheStoredHashIsNotTheCode(): void
    {
        $hash = PairingCode::hash('7K2M9QX4T8WZ3RBN6FJ5HCDV0A');

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        self::assertStringNotContainsString('7K2M9Q', $hash);
    }
}
