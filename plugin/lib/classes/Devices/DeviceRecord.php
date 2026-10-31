<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Devices;

/**
 * One connected device (`app_connect_devices`): the app installation and the
 * token lineage it is bound to.
 *
 * @property int         $id
 * @property string      $device_id
 * @property string      $user_id
 * @property string      $name
 * @property string      $platform          ios | android
 * @property string      $paired_via        pairing | browser
 * @property string      $access_token_id
 * @property string|null $refresh_token_id
 * @property int         $bound_at
 * @property int         $paired_at
 * @property int|null    $revoked_at
 */
final class DeviceRecord extends \SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'app_connect_devices';
        parent::configure($config);
    }
}
