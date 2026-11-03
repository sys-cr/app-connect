<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Privacy;

use AppConnect\Devices\DeviceRecord;
use AppConnect\Pairing\PairingCodeRecord;
use AppConnect\Translation;

/**
 * What app-connect keeps about a person, for the core's data export
 * (`PrivacyPlugin`): connected devices, pairing codes and web-login tickets,
 * as raw rows like the core's own models export them. Codes and tickets are
 * stored as hashes only, so the export cannot reveal a usable one.
 */
final class UserDataExport
{
    public static function export(\StoredUserData $storage): void
    {
        $userId = (string) $storage->getUserId();

        $devices = array_map(
            static fn (DeviceRecord $row): array => $row->toRawArray(),
            DeviceRecord::findBySQL('`user_id` = ? ORDER BY `paired_at`', [$userId])
        );
        $storage->addTabularData(Translation::text('Verbundene Geräte'), 'app_connect_devices', $devices);

        $codes = array_map(
            static fn (PairingCodeRecord $row): array => $row->toRawArray(),
            PairingCodeRecord::findBySQL('`user_id` = ? ORDER BY `mkdate`', [$userId])
        );
        $storage->addTabularData(Translation::text('Kopplungscodes'), 'app_connect_pairing_codes', $codes);

        $tickets = \DBManager::get()->fetchAll(
            'SELECT * FROM `app_connect_web_login_tickets` WHERE `user_id` = ? ORDER BY `created_at`',
            [$userId]
        );
        $storage->addTabularData(
            Translation::text('Links zur Web-Anmeldung'),
            'app_connect_web_login_tickets',
            $tickets
        );
    }

    private function __construct()
    {
    }
}
