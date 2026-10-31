<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Places;

/**
 * Reads the position the room management stores (ISO 6709, e.g.
 * "+52.3822+009.7178+54.3CRSWGS_84/") as decimal degrees. Anything that is not
 * a latitude and a longitude in range yields null.
 */
final class GeoCoordinates
{
    /** @return array{lat: float, lon: float}|null */
    public static function parse(?string $value): ?array
    {
        if ($value === null || preg_match('~^\s*([+-]\d{1,2}(?:\.\d+)?)([+-]\d{1,3}(?:\.\d+)?)~', $value, $m) !== 1) {
            return null;
        }
        $lat = (float) $m[1];
        $lon = (float) $m[2];
        if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
            return null;
        }
        return ['lat' => $lat, 'lon' => $lon];
    }
}
