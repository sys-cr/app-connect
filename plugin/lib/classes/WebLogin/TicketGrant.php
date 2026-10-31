<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

/**
 * What a redeemed ticket hands over: the person, the page to open and the
 * app sign-in the ticket was issued from.
 */
final class TicketGrant
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $returnTo,
        public readonly ?string $accessTokenId,
        public readonly ?int $deviceRowId,
    ) {
    }
}
