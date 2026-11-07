<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * The plugin's pages use the core's content boxes with an h2 as heading, so
 * that the page keeps the core's page title as its only h1. The core styles
 * the heading of a content box only as `section.contentbox header h1`; this
 * style repeats those values for the h2.
 */
final class ContentBox
{
    public const HEADING_STYLE = 'flex: 1 0 0; margin: 0; padding: 10px; border-bottom: none; '
        . 'color: var(--black); font-size: 16px; font-weight: 400; line-height: 24px;';

    private function __construct()
    {
    }
}
