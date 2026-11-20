<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Privacy;

use AppConnect\Translation;

/**
 * What app-connect keeps about a person, for the core's data export
 * (`PrivacyPlugin`): connected devices, pairing codes and web-login tickets.
 *
 * Every column is named. The hashes of codes and tickets stay out: they are
 * no usable secret, but they tell the person nothing either. The ids of the
 * OAuth2 tokens stay in, because the access request under Art. 15 GDPR is
 * to name what is stored about the person.
 */
final class UserDataExport
{
    public const DEVICE_COLUMNS = ['device_id', 'user_id', 'name', 'platform', 'paired_via',
        'access_token_id', 'refresh_token_id', 'paired_at', 'bound_at', 'revoked_at'];
    public const CODE_COLUMNS = ['user_id', 'expires_at', 'consumed_at', 'device_id', 'mkdate'];
    public const TICKET_COLUMNS = ['user_id', 'return_to', 'access_token_id', 'device_row_id',
        'created_at', 'expires_at', 'used_at'];

    public static function export(\StoredUserData $storage): void
    {
        $userId = (string) $storage->getUserId();
        $storage->addTabularData(
            Translation::text('Verbundene Geräte'),
            'app_connect_devices',
            self::rows('app_connect_devices', self::DEVICE_COLUMNS, 'paired_at', $userId)
        );
        $storage->addTabularData(
            Translation::text('Kopplungscodes'),
            'app_connect_pairing_codes',
            self::rows('app_connect_pairing_codes', self::CODE_COLUMNS, 'mkdate', $userId)
        );
        $storage->addTabularData(
            Translation::text('Links zur Web-Anmeldung'),
            'app_connect_web_login_tickets',
            self::rows('app_connect_web_login_tickets', self::TICKET_COLUMNS, 'created_at', $userId)
        );
    }

    /**
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    private static function rows(string $table, array $columns, string $order, string $userId): array
    {
        $list = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
        return \DBManager::get()->fetchAll(
            'SELECT ' . $list . ' FROM `' . $table . '` WHERE `user_id` = ? ORDER BY `' . $order . '`',
            [$userId]
        );
    }

    private function __construct()
    {
    }
}
