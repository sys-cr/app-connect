<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Profile\UserLanguage;
use PHPUnit\Framework\TestCase;

final class UserLanguageTest extends TestCase
{
    private const INSTALLED = ['de_DE', 'en_GB'];

    public function testTheProfileLanguageWinsWhileTheSiteOffersIt(): void
    {
        self::assertSame(
            ['language' => 'en_GB', 'source' => 'profile'],
            UserLanguage::resolve(' en_GB ', self::INSTALLED, 'de_DE')
        );
    }

    public function testWithoutAUsableProfileLanguageTheSiteDefaultApplies(): void
    {
        foreach ([null, '', 'fr_FR'] as $preferred) {
            self::assertSame(
                ['language' => 'de_DE', 'source' => 'default'],
                UserLanguage::resolve($preferred, self::INSTALLED, 'de_DE')
            );
        }
    }
}
