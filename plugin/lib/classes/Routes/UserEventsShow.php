<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Calendar\UserEvents;
use AppConnect\Runtime;
use AppConnect\Settings\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /jsonapi.php/v1/app-connect/user-events – the signed-in person's
 * calendar for two weeks, as the web calendar assembles it: personal entries,
 * course dates of courses shown in the calendar and cancelled course dates.
 * The core route `users/{id}/events` only carries the personal entries and
 * no colour; this route adds the rest without a change to the core.
 */
final class UserEventsShow extends \JsonApi\NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isEnabled()) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        if (($settings->arrayValue(Settings::FEATURES)['userEvents'] ?? true) === false) {
            return JsonErrors::write($response, 404, 'app-connect-feature-off', 'The calendar route is switched off');
        }
        $user = $this->getUser($request);
        if (!$user) {
            return JsonErrors::write($response, 401, 'app-connect-sign-in-required', 'Sign-in required');
        }
        $query = $request->getQueryParams();
        $window = UserEvents::window(((array) ($query['filter'] ?? []))['timestamp'] ?? null, (int) strtotime('today'));
        if ($window === null) {
            return JsonErrors::write($response, 400, 'app-connect-invalid-timestamp', 'filter[timestamp] must be a Unix time');
        }
        $page = UserEvents::page($query['page'] ?? null);
        if ($page === null) {
            return JsonErrors::write($response, 400, 'app-connect-invalid-page', 'page[offset] and page[limit] must be numbers');
        }

        $userId = (string) $user->id;
        $items = array_merge(
            $this->personalEvents($userId, $window),
            $this->courseEvents($userId, $window)
        );

        $document = UserEvents::document($items, $page[0], $page[1]);
        $response->getBody()->write(json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/vnd.api+json')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /**
     * @param array{0: int, 1: int} $window
     * @return list<array{0: int, 1: array<string, mixed>}>
     */
    private function personalEvents(string $userId, array $window): array
    {
        $items = [];
        foreach (\CalendarDateAssignment::getEvents(self::time($window[0]), self::time($window[1]), $userId) as $assignment) {
            $date = $assignment->calendar_date;
            if ($date === null) {
                continue;
            }
            $items[] = [(int) $date->begin, UserEvents::personalEvent([
                'id' => (string) $assignment->calendar_date_id,
                'userId' => $userId,
                'title' => (string) $date->title,
                'description' => (string) $date->description,
                'start' => (int) $date->begin,
                'end' => (int) $date->end,
                'categories' => (string) $date->getCategoryAsString(),
                'location' => (string) $date->location,
                'mkdate' => (int) $date->mkdate,
                'chdate' => (int) $date->chdate,
                'recurrence' => (string) $date->getRepetitionAsString(),
            ])];
        }
        return $items;
    }

    /**
     * @param array{0: int, 1: int} $window
     * @return list<array{0: int, 1: array<string, mixed>}>
     */
    private function courseEvents(string $userId, array $window): array
    {
        $dates = array_merge(
            array_values(\CalendarCourseDate::getEvents(self::time($window[0]), self::time($window[1]), $userId)),
            array_values(\CalendarCourseExDate::getEvents(self::time($window[0]), self::time($window[1]), $userId))
        );
        $colors = $this->colorGroups($userId, array_map(static fn ($date): string => (string) $date->range_id, $dates));

        $items = [];
        foreach ($dates as $date) {
            $courseId = (string) $date->range_id;
            $items[] = [(int) $date->date, UserEvents::courseEvent([
                'id' => (string) $date->id,
                'courseId' => $courseId !== '' ? $courseId : null,
                'title' => $date->course ? (string) $date->course->getFullName() : '',
                'description' => (string) $date->getDescription(),
                'start' => (int) $date->date,
                'end' => (int) $date->end_time,
                'type' => (int) $date->date_typ,
                'categories' => $GLOBALS['TERMIN_TYP'][$date->date_typ]['name'] ?? null,
                'location' => (string) ($date->raum ?? ''),
                'cancelled' => $date instanceof \CourseExDate,
                'color' => $colors[$courseId] ?? 0,
                'mkdate' => (int) $date->mkdate,
                'chdate' => (int) $date->chdate,
                'recurrence' => $date->cycle ? (string) $date->cycle->toString() : '',
            ])];
        }
        return $items;
    }

    /**
     * The person's colour group per course, read once for all courses.
     *
     * @param list<string> $courseIds
     * @return array<string, int>
     */
    private function colorGroups(string $userId, array $courseIds): array
    {
        $courseIds = array_values(array_unique(array_filter($courseIds)));
        if ($courseIds === []) {
            return [];
        }
        $rows = \DBManager::get()->fetchPairs(
            'SELECT `Seminar_id`, `gruppe` FROM `seminar_user` WHERE `user_id` = ? AND `Seminar_id` IN (?)',
            [$userId, $courseIds]
        );
        return array_map([UserEvents::class, 'colorGroup'], $rows);
    }

    private static function time(int $timestamp): \DateTime
    {
        return (new \DateTime())->setTimestamp($timestamp);
    }
}
