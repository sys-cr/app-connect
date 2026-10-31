<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Calendar\IcalSubscription;
use PHPUnit\Framework\TestCase;

final class IcalSubscriptionTest extends TestCase
{
    public function testTheSubscriptionUrlPointsAtTheCoreExport(): void
    {
        self::assertSame(
            'webcal://studip.example/dispatch.php/ical/index/XtlMLwYW',
            IcalSubscription::subscribeUrl('https://studip.example', 'XtlMLwYW')
        );
    }

    public function testASiteInASubdirectoryKeepsItsPath(): void
    {
        self::assertSame(
            'webcal://example.org/studip/dispatch.php/ical/index/XtlMLwYW',
            IcalSubscription::subscribeUrl('https://example.org/studip/', 'XtlMLwYW')
        );
    }

    public function testOnlyHttpsOnTheDefaultPortCanBeSubscribedTo(): void
    {
        // A calendar app fetches webcal over https on 443; anywhere else
        // the URL would quietly point somewhere other than this site.
        foreach (['http://studip.example', 'https://studip.example:8443', 'not a url'] as $baseUrl) {
            self::assertNull(IcalSubscription::subscribeUrl($baseUrl, 'XtlMLwYW'));
        }
    }

    public function testAKeyIsEightOrSoLettersAndDigits(): void
    {
        self::assertTrue(IcalSubscription::isValidKey('XtlMLwYW'));
        self::assertTrue(IcalSubscription::isValidKey('abc123'));
        foreach (['', 'short', 'with space', 'slash/es', 'ümlaut7', str_repeat('a', 33), null, 42] as $key) {
            self::assertFalse(IcalSubscription::isValidKey($key));
        }
    }

    public function testThePayloadNamesTheKeyAndTheUrl(): void
    {
        self::assertSame(
            [
                'key' => 'XtlMLwYW',
                'subscribeUrl' => 'webcal://studip.example/dispatch.php/ical/index/XtlMLwYW',
                'active' => true,
            ],
            IcalSubscription::payload('https://studip.example', 'XtlMLwYW')
        );
    }

    public function testThePayloadKeepsTheKeyWhereNoUrlCanBeBuilt(): void
    {
        // The person can still paste the key into a calendar by hand.
        $payload = IcalSubscription::payload('http://studip.example', 'XtlMLwYW');

        self::assertSame('XtlMLwYW', $payload['key']);
        self::assertNull($payload['subscribeUrl']);
    }
}
