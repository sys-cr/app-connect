<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Pairing;

/**
 * One pairing code (`app_connect_pairing_codes`). Only the hash is stored.
 *
 * @property string      $code_hash
 * @property string      $user_id
 * @property int         $expires_at
 * @property int|null    $consumed_at
 * @property string|null $device_id
 * @property int         $mkdate
 */
final class PairingCodeRecord extends \SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'app_connect_pairing_codes';
        parent::configure($config);
    }
}
