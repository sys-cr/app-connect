<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * Calls a PHP function that reports bad input both by its return value and
 * by a warning, such as dns_get_record() on a DNS failure. Only warnings and
 * notices are ignored, and only during the call; the caller checks the
 * return value. Errors and exceptions pass.
 */
final class Quietly
{
    /**
     * @template T
     * @param \Closure(): T $call
     * @return T
     */
    public static function call(\Closure $call): mixed
    {
        set_error_handler(static fn (): bool => true, E_WARNING | E_NOTICE);
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }

    private function __construct()
    {
    }
}
