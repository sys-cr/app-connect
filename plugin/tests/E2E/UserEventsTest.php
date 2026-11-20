<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/** The course dates of the calendar route, with title, cycle and cancellations (#34). */
final class UserEventsTest extends E2ETestCase
{
    public function testTheDatesOfACourseCarryTitleCycleAndCancellation(): void
    {
        $course = self::helper('course', self::AUTOR);

        $response = self::request('GET', self::url('jsonapi.php/v1/app-connect/user-events'), null, [
            'Authorization: Bearer ' . self::pair(self::AUTOR)['access_token'],
        ]);
        self::assertSame(200, $response->status, $response->body);
        $events = array_values(array_filter(
            $response->json()['data'],
            static fn (array $event): bool => ($event['relationships']['owner']['data']['id'] ?? null) === $course['course']
        ));

        self::assertCount(4, $events, 'two dates of the cycle, a single one and a cancelled one');
        foreach ($events as $event) {
            self::assertSame('course-events', $event['type']);
            self::assertSame($course['name'], $event['attributes']['title']);
        }
        $recurrences = array_map(static fn (array $event): string => $event['attributes']['recurrence'], $events);
        sort($recurrences);
        self::assertSame(['', $course['cycle'], $course['cycle'], $course['cycle']], $recurrences);
        self::assertSame([false, false, false, true], self::sorted(array_map(static fn (array $event): bool => $event['attributes']['is-cancelled'], $events)));
    }

    /**
     * @param list<bool> $values
     * @return list<bool>
     */
    private static function sorted(array $values): array
    {
        sort($values);
        return $values;
    }
}
