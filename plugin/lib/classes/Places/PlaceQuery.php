<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Places;

/**
 * Input and output rules of the place route: which date ids a request may
 * ask for, and what a place entry may carry.
 */
final class PlaceQuery
{
    public const MAX_IDS = 50;

    /**
     * The date ids of `filter[ids]`: comma separated, trimmed, unique,
     * Stud.IP ids only. Null when there are none or more than {@see MAX_IDS}.
     *
     * @return list<string>|null
     */
    public static function ids(mixed $filter): ?array
    {
        if (!is_string($filter)) {
            return null;
        }
        $ids = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $filter)),
            static fn (string $id): bool => preg_match('/^[0-9a-f]{32}$/', $id) === 1
        )));
        return $ids === [] || count($ids) > self::MAX_IDS ? null : $ids;
    }

    /**
     * The booked rooms of a course date. Stud.IP 6.2 books several rooms per
     * date (`getRooms()`); 6.0 and 6.1 know one at most (`getRoom()`).
     *
     * @return list<object>
     */
    public static function rooms(object $date): array
    {
        if (method_exists($date, 'getRooms')) {
            return array_values(array_filter((array) $date->getRooms(), 'is_object'));
        }
        if (method_exists($date, 'getRoom')) {
            $room = $date->getRoom();
            return is_object($room) ? [$room] : [];
        }
        return [];
    }

    /**
     * One place entry; null without a name. Coordinates and link are only
     * added when valid (link: https only).
     *
     * @param array{lat: float, lon: float}|null $coordinates
     * @return array<string, mixed>|null
     */
    public static function place(?string $name, ?array $coordinates, ?string $link): ?array
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        $place = ['name' => $name];
        if ($coordinates !== null) {
            $place['coordinates'] = $coordinates;
        }
        $link = trim((string) $link);
        if (preg_match('~^https://[^\s/?#@]+(/\S*)?$~', $link) === 1) {
            $place['link'] = $link;
        }
        return $place;
    }
}
