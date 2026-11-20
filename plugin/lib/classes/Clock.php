<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * The current time for the services that count windows and expiry. They
 * take an optional closure, so a test can set the time; without one it is
 * the system time.
 */
final class Clock
{
    /** @param (\Closure(): int)|null $source */
    public static function now(?\Closure $source): int
    {
        return $source !== null ? $source() : time();
    }

    private function __construct()
    {
    }
}
