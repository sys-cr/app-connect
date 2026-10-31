<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Places\GeoCoordinates;
use AppConnect\Places\PlaceQuery;
use PHPUnit\Framework\TestCase;

final class PlacesTest extends TestCase
{
    public function testStoredPositionsBecomeDecimalDegrees(): void
    {
        self::assertSame(['lat' => 52.3822, 'lon' => 9.7178], GeoCoordinates::parse('+52.3822+009.7178+54.3CRSWGS_84/'));
        self::assertSame(['lat' => -33.9, 'lon' => -70.25], GeoCoordinates::parse('-33.9-070.25/'));
    }

    public function testInvalidOrOutOfRangePositionsAreDropped(): void
    {
        foreach ([null, '', 'Hannover', '+91.0+009.0/', '+52.0+181.0/', '52.1 9.7'] as $value) {
            self::assertNull(GeoCoordinates::parse($value), var_export($value, true));
        }
    }

    public function testIdsAreTrimmedUniqueAndLimited(): void
    {
        $a = str_repeat('a', 32);
        $b = str_repeat('b', 32);
        self::assertSame([$a, $b], PlaceQuery::ids(" $a,$b,$a,not-an-id,"));
        self::assertNull(PlaceQuery::ids(''));
        self::assertNull(PlaceQuery::ids(['x']));
        $many = implode(',', array_map(static fn (int $i): string => md5((string) $i), range(1, PlaceQuery::MAX_IDS + 1)));
        self::assertNull(PlaceQuery::ids($many));
        $max = implode(',', array_map(static fn (int $i): string => md5((string) $i), range(1, PlaceQuery::MAX_IDS)));
        self::assertCount(PlaceQuery::MAX_IDS, PlaceQuery::ids($max));
    }

    public function testPlaceCarriesOnlyValidParts(): void
    {
        self::assertNull(PlaceQuery::place('  ', null, null));
        self::assertSame(['name' => 'HS 1'], PlaceQuery::place(' HS 1 ', null, 'http://uni.example/raum'));
        self::assertSame(
            ['name' => 'A310', 'coordinates' => ['lat' => 1.0, 'lon' => 2.0], 'link' => 'https://uni.example/raum?r=A310'],
            PlaceQuery::place('A310', ['lat' => 1.0, 'lon' => 2.0], 'https://uni.example/raum?r=A310')
        );
        self::assertSame(['name' => 'A310'], PlaceQuery::place('A310', null, 'javascript:alert(1)'));
    }

    public function testRoomsComeFromEitherCoreApi(): void
    {
        $room = new \stdClass();
        $several = new class ($room) {
            public function __construct(private object $room) {}
            public function getRooms(): array { return [$this->room, null]; }
        };
        $single = new class ($room) {
            public function __construct(private ?object $room) {}
            public function getRoom(): ?object { return $this->room; }
        };
        $none = new class (null) {
            public function __construct(private ?object $room) {}
            public function getRoom(): ?object { return $this->room; }
        };

        self::assertSame([$room], PlaceQuery::rooms($several));
        self::assertSame([$room], PlaceQuery::rooms($single));
        self::assertSame([], PlaceQuery::rooms($none));
        self::assertSame([], PlaceQuery::rooms(new \stdClass()));
    }
}
