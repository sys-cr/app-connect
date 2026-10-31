<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Devices;

/**
 * The device data an app sends when it connects: its per-instance UUID, a
 * display name and the platform. Built only from valid input.
 */
final class DeviceInput
{
    public const PLATFORMS = ['ios', 'android'];
    public const NAME_MAX_LENGTH = 64;

    private function __construct(
        public readonly string $deviceId,
        public readonly string $name,
        public readonly string $platform,
    ) {
    }

    /** Returns null when any value is missing or malformed. */
    public static function fromValues(mixed $deviceId, mixed $name, mixed $platform): ?self
    {
        if (!is_string($deviceId) || !is_string($name) || !is_string($platform)) {
            return null;
        }
        $deviceId = strtolower(trim($deviceId));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $deviceId) !== 1) {
            return null;
        }
        // Control characters would end up in the device list and the mail.
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name));
        if ($name === '' || mb_strlen($name) > self::NAME_MAX_LENGTH) {
            return null;
        }
        if (!in_array($platform, self::PLATFORMS, true)) {
            return null;
        }
        return new self($deviceId, $name, $platform);
    }
}
