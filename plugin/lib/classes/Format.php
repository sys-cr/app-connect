<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/** Times on the plugin's pages, in the format of the current language. */
final class Format
{
    public static function dateTime(int $time): string
    {
        return date(Translation::text('d.m.Y H:i'), $time);
    }

    public static function date(int $time): string
    {
        return date(Translation::text('d.m.Y'), $time);
    }

    private function __construct()
    {
    }
}
