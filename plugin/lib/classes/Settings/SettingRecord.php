<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/**
 * One row of `app_connect_settings`.
 *
 * @property string $setting_key
 * @property string $value JSON
 * @property int    $chdate
 */
final class SettingRecord extends \SimpleORMap
{
    protected static function configure($config = [])
    {
        $config['db_table'] = 'app_connect_settings';
        parent::configure($config);
    }
}
