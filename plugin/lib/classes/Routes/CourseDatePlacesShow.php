<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Places\GeoCoordinates;
use AppConnect\Places\PlaceQuery;
use AppConnect\Runtime;
use AppConnect\Settings\OperatorConfig;
use AppConnect\Settings\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /jsonapi.php/v1/app-connect/course-dates/places?filter[ids]=… – where
 * course dates take place: the booked rooms with coordinates (room, else its
 * parents such as building and location) and a link to the site's room
 * finder, or the free-text room. The core API only carries the free text.
 * Dates the person may not see are left out without a word.
 */
final class CourseDatePlacesShow extends \JsonApi\NonJsonApiController
{
    /** Parent levels searched for coordinates: room → building → location. */
    private const COORDINATE_DEPTH = 3;

    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isEnabled()) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        if (($settings->arrayValue(Settings::FEATURES)['eventPlaces'] ?? true) === false) {
            return JsonErrors::write($response, 404, 'app-connect-feature-off', 'Places of course dates are switched off');
        }
        $user = $this->getUser($request);
        if (!$user) {
            return JsonErrors::write($response, 401, 'app-connect-sign-in-required', 'Sign-in required');
        }
        $filter = (array) ($request->getQueryParams()['filter'] ?? []);
        $ids = PlaceQuery::ids($filter['ids'] ?? null);
        if ($ids === null) {
            return JsonErrors::write(
                $response,
                400,
                'app-connect-invalid-ids',
                sprintf('filter[ids] needs 1 to %d date ids', PlaceQuery::MAX_IDS)
            );
        }

        $linkProperty = trim((string) \Config::get()->getValue(OperatorConfig::ROOM_FINDER_PROPERTY));
        $data = [];
        foreach (\CourseDate::findMany($ids) as $date) {
            if (!$GLOBALS['perm']->have_studip_perm('user', $date->range_id, $user->id)) {
                continue;
            }
            $data[] = ['dateId' => (string) $date->id, 'places' => $this->places($date, $linkProperty)];
        }

        $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'private, no-store');
    }

    /** @return list<array<string, mixed>> */
    private function places(\CourseDate $date, string $linkProperty): array
    {
        $places = [];
        foreach (PlaceQuery::rooms($date) as $room) {
            if (!$room instanceof \Resource) {
                continue;
            }
            $place = PlaceQuery::place(
                $room->getFullName(),
                $this->coordinates($room),
                $linkProperty !== '' ? $this->propertyText($room, $linkProperty) : null
            );
            if ($place !== null) {
                $places[] = $place;
            }
        }
        if ($places === []) {
            $place = PlaceQuery::place((string) $date->raum, null, null);
            if ($place !== null) {
                $places[] = $place;
            }
        }
        return $places;
    }

    /** @return array{lat: float, lon: float}|null */
    private function coordinates(\Resource $resource): ?array
    {
        for ($level = 0; $level < self::COORDINATE_DEPTH && $resource instanceof \Resource; $level++) {
            $coordinates = GeoCoordinates::parse($this->propertyText($resource, 'geo_coordinates'));
            if ($coordinates !== null) {
                return $coordinates;
            }
            $resource = $resource->parent_id ? $resource->parent : null;
        }
        return null;
    }

    /** The stored value of a resource property, without creating it. */
    private function propertyText(\Resource $resource, string $name): ?string
    {
        try {
            if (!$resource->propertyExists($name)) {
                return null;
            }
            $property = $resource->getPropertyObject($name);
        } catch (\Throwable $exception) {
            return null;
        }
        return $property instanceof \ResourceProperty && is_string($property->state) ? $property->state : null;
    }
}
