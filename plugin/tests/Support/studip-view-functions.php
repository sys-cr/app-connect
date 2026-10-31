<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

// Minimal stand-ins for the Stud.IP view helpers, so that plain view partials
// can be rendered without a Stud.IP installation. Loaded only by tests.

if (!function_exists('htmlReady')) {
    function htmlReady($what): string
    {
        return htmlspecialchars((string) $what, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('_')) {
    function _(string $message): string
    {
        return $message;
    }
}
