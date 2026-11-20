<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * Work that must not hold up the response, such as a mail: it runs when the
 * request ends. Under PHP-FPM fastcgi_finish_request() hands the response to
 * the client first; elsewhere the task runs right after the output.
 */
final class AfterResponse
{
    /**
     * @param \Closure(): void $task handles its own failures
     * @param (\Closure(\Closure(): void): void)|null $register register_shutdown_function by default
     */
    public static function run(\Closure $task, ?\Closure $register = null): void
    {
        ($register ?? register_shutdown_function(...))(static function () use ($task): void {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $task();
        });
    }

    private function __construct()
    {
    }
}
