<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Connect;

/**
 * Outcome of parsing a connect link or landing page URL. A rejection names the
 * first rule the input broke (contract/test-vectors/connect-link.json "reasons").
 */
final class ConnectLinkResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly ?string $host,
        public readonly ?int $port,
        public readonly ?string $code,
        public readonly ?string $reason,
    ) {
    }

    public static function accept(string $host, ?int $port, ?string $code): self
    {
        return new self(true, $host, $port, $code, null);
    }

    public static function reject(string $reason): self
    {
        return new self(false, null, null, null, $reason);
    }
}
