<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

use AppConnect\Privacy\UserDataExport;

/** The plugin's part of the core's data export names its columns (#24). */
final class DataExportTest extends E2ETestCase
{
    public function testTheExportHoldsTheNamedColumnsAndNoHashes(): void
    {
        $tokens = self::pair(self::AUTOR);
        $link = self::request('POST', self::url('jsonapi.php/v1/app-connect/web-login'), '{}', [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $tokens['access_token'],
        ]);
        self::assertSame(201, $link->status, $link->body);

        $tables = array_column(self::helper('export', self::AUTOR), 'rows', 'key');

        foreach ([
            'app_connect_devices' => UserDataExport::DEVICE_COLUMNS,
            'app_connect_pairing_codes' => UserDataExport::CODE_COLUMNS,
            'app_connect_web_login_tickets' => UserDataExport::TICKET_COLUMNS,
        ] as $table => $columns) {
            self::assertNotEmpty($tables[$table] ?? [], $table . ' is exported');
            foreach ($tables[$table] as $row) {
                self::assertSame($columns, array_keys($row), $table);
            }
        }
        self::assertContains($tokens['device_id'], array_column($tables['app_connect_devices'], 'device_id'));
        self::assertStringNotContainsString('_hash', (string) json_encode($tables));
    }
}
