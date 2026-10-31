<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Calendar;

/**
 * The person's calendar as the web calendar shows it (contract/user-events.md):
 * personal entries, course dates of courses shown in the calendar and
 * cancelled course dates, in the resource shapes of the core route
 * `users/{id}/events` (`calendar-events`, `course-events`). Course events
 * also carry the person's colour group of the course (`seminar_user.gruppe`).
 * Pure functions; the route reads the models.
 */
final class UserEvents
{
    /** Length of the window, as in the core route. */
    public const WINDOW = '+2 weeks';
    public const DEFAULT_LIMIT = 30;
    public const MAX_LIMIT = 100;

    /**
     * Start and end of the window: from `filter[timestamp]` (Unix time, as in
     * the core route) or today's midnight, two weeks long. Null for a value
     * that is not a non-negative integer.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function window(mixed $timestamp, int $todayMidnight): ?array
    {
        if ($timestamp === null || $timestamp === '') {
            $start = $todayMidnight;
        } elseif (is_string($timestamp) && preg_match('/^\d{1,11}$/', $timestamp) === 1) {
            $start = (int) $timestamp;
        } else {
            return null;
        }
        return [$start, (int) strtotime(self::WINDOW, $start)];
    }

    /**
     * Offset and limit from `page[offset]` and `page[limit]`. Null for values
     * that are not non-negative integers or a limit of 0.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function page(mixed $page): ?array
    {
        $page = is_array($page) ? $page : [];
        $offset = $page['offset'] ?? '0';
        $limit = $page['limit'] ?? (string) self::DEFAULT_LIMIT;
        if (!is_string($offset) || preg_match('/^\d{1,9}$/', $offset) !== 1
            || !is_string($limit) || preg_match('/^\d{1,9}$/', $limit) !== 1 || (int) $limit === 0) {
            return null;
        }
        return [(int) $offset, min((int) $limit, self::MAX_LIMIT)];
    }

    /** Colour group 0–8 as Stud.IP stores it; anything else counts as 0. */
    public static function colorGroup(mixed $value): int
    {
        $group = is_numeric($value) ? (int) $value : 0;
        return $group >= 0 && $group <= 8 ? $group : 0;
    }

    /**
     * A course date or cancelled course date as `course-events`.
     *
     * @param array{id: string, courseId: ?string, title: string, description: string, start: int, end: int,
     *     type: int, categories: ?string, location: string, cancelled: bool, color: int, mkdate: int,
     *     chdate: int, recurrence: string} $date
     * @return array<string, mixed>
     */
    public static function courseEvent(array $date): array
    {
        $resource = [
            'type' => 'course-events',
            'id' => $date['id'],
            'attributes' => [
                'title' => $date['title'],
                'description' => $date['description'],
                'start' => date('c', $date['start']),
                'end' => date('c', $date['end']),
                'type' => $date['type'],
                'categories' => $date['categories'],
                'location' => $date['location'],
                'is-cancelled' => $date['cancelled'],
                'color' => self::colorGroup($date['color']),
                'mkdate' => date('c', $date['mkdate']),
                'chdate' => date('c', $date['chdate']),
                'recurrence' => $date['recurrence'],
            ],
        ];
        if ($date['courseId'] !== null) {
            $resource['relationships'] = ['owner' => ['data' => ['type' => 'courses', 'id' => $date['courseId']]]];
        }
        return $resource;
    }

    /**
     * A personal calendar entry as `calendar-events`.
     *
     * @param array{id: string, userId: string, title: string, description: string, start: int, end: int,
     *     categories: string, location: string, mkdate: int, chdate: int, recurrence: string} $entry
     * @return array<string, mixed>
     */
    public static function personalEvent(array $entry): array
    {
        return [
            'type' => 'calendar-events',
            'id' => $entry['id'],
            'attributes' => [
                'title' => $entry['title'],
                'description' => $entry['description'],
                'start' => date('c', $entry['start']),
                'end' => date('c', $entry['end']),
                'categories' => $entry['categories'],
                'location' => $entry['location'],
                'mkdate' => date('c', $entry['mkdate']),
                'chdate' => date('c', $entry['chdate']),
                'recurrence' => $entry['recurrence'],
            ],
            'relationships' => ['owner' => ['data' => ['type' => 'users', 'id' => $entry['userId']]]],
        ];
    }

    /**
     * The response document: resources sorted by start time (Unix time, so
     * a change of the UTC offset does not reorder them), one page of them and
     * the paging data of the core routes.
     *
     * @param list<array{0: int, 1: array<string, mixed>}> $items start time and resource
     * @return array<string, mixed>
     */
    public static function document(array $items, int $offset, int $limit): array
    {
        usort($items, static fn (array $a, array $b): int => [$a[0], $a[1]['id']] <=> [$b[0], $b[1]['id']]);
        return [
            'data' => array_column(array_slice($items, $offset, $limit), 1),
            'meta' => ['page' => ['offset' => $offset, 'limit' => $limit, 'total' => count($items)]],
        ];
    }
}
