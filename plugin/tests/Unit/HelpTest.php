<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Help;
use PHPUnit\Framework\TestCase;

final class HelpTest extends TestCase
{
    public function testEveryPageHasHelp(): void
    {
        self::assertCount(2, Help::texts(Help::GET));
        self::assertCount(4, Help::texts(Help::CONNECT));
    }

    /** Without pairing the connect page does not talk about QR codes. */
    public function testConnectHelpFollowsThePairingSwitch(): void
    {
        self::assertStringContainsString('QR-Code', implode(' ', Help::texts(Help::CONNECT, true)));
        self::assertStringNotContainsString('QR-Code', implode(' ', Help::texts(Help::CONNECT, false)));
    }

    public function testTextsHaveNoDashes(): void
    {
        foreach ([Help::texts(Help::GET), Help::texts(Help::CONNECT, true), Help::texts(Help::CONNECT, false)] as $texts) {
            foreach ($texts as $text) {
                self::assertDoesNotMatchRegularExpression('/[–—]/u', $text);
            }
        }
    }
}
