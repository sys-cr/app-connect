<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Logo\LogoImage;
use AppConnect\Logo\LogoRejected;
use AppConnect\Logo\LogoStatusText;
use AppConnect\Logo\StoredLogo;
use PHPUnit\Framework\TestCase;

/** The messages of the logo box on "Stud.App Verbindung" (#31). */
final class LogoStatusTextTest extends TestCase
{
    private const FETCHED = 1_795_000_000;
    private const CHECKED = 1_795_003_600;

    public function testNotFetchedYet(): void
    {
        self::assertSame([['info', 'Das Logo wurde noch nicht abgerufen.']], LogoStatusText::describe(null));
    }

    public function testAFetchedLogo(): void
    {
        $messages = LogoStatusText::describe(new StoredLogo('https://example.org/logo.png', self::image(), self::FETCHED, self::CHECKED, null));

        self::assertSame([['success', sprintf(
            'Abgerufen am %s (PNG, 64 × 48 Pixel). Die Apps erhalten es von diesem Server.',
            date('d.m.Y H:i', self::FETCHED)
        )]], $messages);
    }

    public function testAFailedRefetchKeepsTheLastCopy(): void
    {
        $messages = LogoStatusText::describe(new StoredLogo('https://example.org/logo.png', self::image(), self::FETCHED, self::CHECKED, LogoRejected::TOO_LARGE));

        self::assertSame(['success', 'warning'], array_column($messages, 0));
        self::assertSame(sprintf(
            'Der letzte Abruf am %s ist fehlgeschlagen: %s Die Apps erhalten weiter die Fassung oben.',
            date('d.m.Y H:i', self::CHECKED),
            LogoStatusText::reason(LogoRejected::TOO_LARGE)
        ), $messages[1][1]);
    }

    public function testNoLogo(): void
    {
        $messages = LogoStatusText::describe(new StoredLogo('https://example.org/logo.png', null, null, self::CHECKED, LogoRejected::INTERNAL));

        self::assertSame([['error', sprintf(
            'Die Apps erhalten kein Logo. Der Abruf am %s ist fehlgeschlagen: %s',
            date('d.m.Y H:i', self::CHECKED),
            LogoStatusText::reason(LogoRejected::INTERNAL)
        )]], $messages);
    }

    public function testAnUpload(): void
    {
        $messages = LogoStatusText::describe(new StoredLogo(StoredLogo::UPLOAD, self::image(), self::FETCHED, self::FETCHED, null));

        self::assertSame('success', $messages[0][0]);
        self::assertStringStartsWith('Hochgeladen am ' . date('d.m.Y H:i', self::FETCHED) . ' (PNG, 64 × 48 Pixel).', $messages[0][1]);
        self::assertCount(1, $messages);
    }

    public function testTheViewHasNoTimesOrTypesOfItsOwn(): void
    {
        $view = (string) file_get_contents(__DIR__ . '/../../views/connect/index.php');

        self::assertDoesNotMatchRegularExpression('/(?<![:\w])date\(/', $view);
        self::assertStringNotContainsString('time()', $view);
        self::assertStringNotContainsString("'image/png'", $view);
    }

    private static function image(): LogoImage
    {
        return LogoImage::fromBytes((string) file_get_contents(__DIR__ . '/../Fixtures/logo/logo.png'));
    }
}
