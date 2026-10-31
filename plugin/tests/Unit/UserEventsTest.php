<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Calendar\UserEvents;
use AppConnect\OAuth2\ClientProvisioner;
use PHPUnit\Framework\TestCase;

final class UserEventsTest extends TestCase
{
    private const MIDNIGHT = 1_800_000_000;

    public function testWindowStartsAtTheTimestampOrTodayAndLastsTwoWeeks(): void
    {
        self::assertSame([self::MIDNIGHT, strtotime('+2 weeks', self::MIDNIGHT)], UserEvents::window(null, self::MIDNIGHT));
        self::assertSame([self::MIDNIGHT, strtotime('+2 weeks', self::MIDNIGHT)], UserEvents::window('', self::MIDNIGHT));
        self::assertSame([1_700_000_000, strtotime('+2 weeks', 1_700_000_000)], UserEvents::window('1700000000', self::MIDNIGHT));
    }

    public function testWindowRefusesValuesThatAreNoUnixTime(): void
    {
        foreach (['-1', 'abc', '1.5', '17e8', ' 1', '123456789012', ['1'], 17] as $value) {
            self::assertNull(UserEvents::window($value, self::MIDNIGHT));
        }
    }

    public function testPageDefaultsAndCapsTheLimit(): void
    {
        self::assertSame([0, UserEvents::DEFAULT_LIMIT], UserEvents::page(null));
        self::assertSame([10, 5], UserEvents::page(['offset' => '10', 'limit' => '5']));
        self::assertSame([0, UserEvents::MAX_LIMIT], UserEvents::page(['limit' => '100000']));
        foreach ([['limit' => '0'], ['offset' => '-1'], ['limit' => 'x'], ['offset' => ['1']]] as $page) {
            self::assertNull(UserEvents::page($page));
        }
    }

    public function testColourGroupIsZeroToEight(): void
    {
        self::assertSame(3, UserEvents::colorGroup('3'));
        self::assertSame(8, UserEvents::colorGroup(8));
        self::assertSame(0, UserEvents::colorGroup(null));
        self::assertSame(0, UserEvents::colorGroup('9'));
        self::assertSame(0, UserEvents::colorGroup('-1'));
    }

    public function testCourseEventHasTheCoreShapeWithColour(): void
    {
        $event = UserEvents::courseEvent(self::courseDate(['color' => '4', 'cancelled' => true]));

        self::assertSame('course-events', $event['type']);
        self::assertSame('d1', $event['id']);
        self::assertSame(
            ['title', 'description', 'start', 'end', 'type', 'categories', 'location', 'is-cancelled', 'color',
             'mkdate', 'chdate', 'recurrence'],
            array_keys($event['attributes'])
        );
        self::assertSame(4, $event['attributes']['color']);
        self::assertTrue($event['attributes']['is-cancelled']);
        self::assertSame(date('c', 1_800_003_600), $event['attributes']['start']);
        self::assertSame(['data' => ['type' => 'courses', 'id' => 'c1']], $event['relationships']['owner']);
    }

    public function testCourseEventWithoutCourseHasNoOwner(): void
    {
        self::assertArrayNotHasKey('relationships', UserEvents::courseEvent(self::courseDate(['courseId' => null])));
    }

    public function testPersonalEventHasTheCoreShape(): void
    {
        $event = UserEvents::personalEvent([
            'id' => 'p1', 'userId' => 'u1', 'title' => 'Lerngruppe', 'description' => '', 'start' => 1_800_000_000,
            'end' => 1_800_007_200, 'categories' => '', 'location' => 'Bibliothek', 'mkdate' => 1, 'chdate' => 2,
            'recurrence' => '',
        ]);

        self::assertSame('calendar-events', $event['type']);
        self::assertSame(
            ['title', 'description', 'start', 'end', 'categories', 'location', 'mkdate', 'chdate', 'recurrence'],
            array_keys($event['attributes'])
        );
        self::assertSame(['data' => ['type' => 'users', 'id' => 'u1']], $event['relationships']['owner']);
    }

    public function testDocumentSortsByTimeAndPages(): void
    {
        $items = [
            [300, ['id' => 'c', 'type' => 'x']],
            [100, ['id' => 'b', 'type' => 'x']],
            [100, ['id' => 'a', 'type' => 'x']],
            [200, ['id' => 'd', 'type' => 'x']],
        ];

        $document = UserEvents::document($items, 1, 2);

        self::assertSame(['b', 'd'], array_column($document['data'], 'id'));
        self::assertSame(['page' => ['offset' => 1, 'limit' => 2, 'total' => 4]], $document['meta']);
    }

    public function testRecordedBaseUrlMustBeAPlainAddress(): void
    {
        self::assertTrue(ClientProvisioner::isBaseUrl('https://studip.example.org'));
        self::assertTrue(ClientProvisioner::isBaseUrl('https://studip.example.org/studip/'));
        self::assertTrue(ClientProvisioner::isBaseUrl('http://localhost:8062/'));
        foreach (['', '/', 'studip.example.org', 'https://user@evil.example/', 'https://a b/', 'javascript:alert(1)',
                  'https://studip.example.org/?x', null] as $value) {
            self::assertFalse(ClientProvisioner::isBaseUrl($value));
        }
    }

    /** @return array<string, mixed> */
    private static function courseDate(array $overrides): array
    {
        return array_merge([
            'id' => 'd1', 'courseId' => 'c1', 'title' => 'Mathematik I', 'description' => '', 'start' => 1_800_003_600,
            'end' => 1_800_009_000, 'type' => 1, 'categories' => 'Sitzung', 'location' => 'Raum 1', 'cancelled' => false,
            'color' => 0, 'mkdate' => 1, 'chdate' => 2, 'recurrence' => '',
        ], $overrides);
    }
}
