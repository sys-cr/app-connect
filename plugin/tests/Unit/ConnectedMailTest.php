<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Mail\ConnectedMail;
use PHPUnit\Framework\TestCase;

final class ConnectedMailTest extends TestCase
{
    private const URL = 'https://studip.example.org/plugins.php/appconnect/connect';

    public function testGermanMailNamesDevicePlatformWayAndTheProfileLink(): void
    {
        $texts = ConnectedMail::texts(false, 'Adas iPhone', 'ios', 'pairing', 1_790_000_000, self::URL);

        self::assertSame('Gerät verbunden', $texts['subject']);
        self::assertStringContainsString('Gerät: Adas iPhone (iOS)', $texts['body']);
        self::assertStringContainsString('Verbunden per: QR-Code', $texts['body']);
        self::assertStringContainsString(self::URL, $texts['body']);
    }

    public function testEnglishMailForBrowserSignIn(): void
    {
        $texts = ConnectedMail::texts(true, 'Pixel', 'android', 'browser', 1_790_000_000, self::URL);

        self::assertSame('Device connected', $texts['subject']);
        self::assertStringContainsString('Device: Pixel (Android)', $texts['body']);
        self::assertStringContainsString('Connected via: browser sign-in', $texts['body']);
    }

    public function testTheMailCarriesNoCodeOrAddress(): void
    {
        $texts = ConnectedMail::texts(false, 'Phone', 'ios', 'pairing', 1_790_000_000, self::URL);

        self::assertDoesNotMatchRegularExpression('/[0-9A-HJKMNP-TV-Z]{26}/', $texts['body']);
        self::assertDoesNotMatchRegularExpression('/\b\d{1,3}(\.\d{1,3}){3}\b/', $texts['body']);
    }
}
