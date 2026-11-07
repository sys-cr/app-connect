<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

/**
 * The lineage step a revocation request names: the access token and, for a
 * refresh token, the refresh token itself. The ids are those of the core
 * tables `oauth2_access_tokens` and `oauth2_refresh_tokens`.
 */
final class RevocableToken
{
    public function __construct(
        public readonly string $accessTokenId,
        public readonly ?string $refreshTokenId,
    ) {
    }
}
