<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/** The answer to one {@see HttpFetcher::get()}. */
final class HttpReply
{
    /**
     * @param string|null $location  the Location header, for a redirect
     * @param bool        $truncated the body was longer than the limit and cut off
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly ?string $location = null,
        public readonly bool $truncated = false,
    ) {
    }
}
