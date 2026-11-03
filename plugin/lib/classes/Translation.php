<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * Translation in the plugin's text domain (`localedomain` in plugin.manifest,
 * catalogues in locale/). The plugin class, its controllers and views use the
 * core's plugin translation (`$plugin->_()`, `$_` in views); this class serves
 * the code that runs without a plugin instance: the cron job and the mail.
 * Like the core's plugin translation it falls back to the
 * core catalogue and returns the German text when neither has an entry.
 */
final class Translation
{
    public const DOMAIN = 'app-connect';

    private static bool $bound = false;

    public static function text(string $message): string
    {
        if (!function_exists('dgettext')) {
            return $message;
        }
        if (!self::$bound) {
            bindtextdomain(self::DOMAIN, dirname(__DIR__, 2) . '/locale');
            bind_textdomain_codeset(self::DOMAIN, 'UTF-8');
            self::$bound = true;
        }
        $translated = dgettext(self::DOMAIN, $message);
        return $translated === $message ? _($message) : $translated;
    }

    private function __construct()
    {
    }
}
