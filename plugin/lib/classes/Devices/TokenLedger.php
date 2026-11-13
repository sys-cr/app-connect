<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Devices;

/**
 * Reads and revokes tokens in the core OAuth2 tables (`oauth2_access_tokens`,
 * `oauth2_refresh_tokens`). This is one of the narrow uses of core internals
 * the device binding needs; everything is scoped to one user and the app client.
 */
final class TokenLedger
{
    /** The refresh token issued together with an access token, if any. */
    public function refreshTokenFor(string $accessTokenId): ?string
    {
        $id = \DBManager::get()->fetchColumn(
            'SELECT `id` FROM `oauth2_refresh_tokens` WHERE `access_token_id` = ? ORDER BY `expires_at` DESC LIMIT 1',
            [$accessTokenId]
        );
        return $id === false || $id === null ? null : (string) $id;
    }

    public function isRefreshTokenRevoked(string $refreshTokenId): bool
    {
        $revoked = \DBManager::get()->fetchColumn(
            'SELECT `revoked` FROM `oauth2_refresh_tokens` WHERE `id` = ?',
            [$refreshTokenId]
        );
        return $revoked === false || (int) $revoked === 1;
    }

    public function isAccessTokenRevoked(string $accessTokenId): bool
    {
        $revoked = \DBManager::get()->fetchColumn(
            'SELECT `revoked` FROM `oauth2_access_tokens` WHERE `id` = ?',
            [$accessTokenId]
        );
        return $revoked === false || (int) $revoked === 1;
    }

    /**
     * Access tokens of the user and client that are still usable.
     *
     * @return list<string>
     */
    public function validAccessTokens(string $userId, string $clientId): array
    {
        $ids = \DBManager::get()->fetchFirst(
            'SELECT `id` FROM `oauth2_access_tokens`
              WHERE `user_id` = ? AND `client_id` = ? AND `revoked` = 0
                AND (`expires_at` IS NULL OR `expires_at` > UNIX_TIMESTAMP()
                     OR EXISTS (SELECT 1 FROM `oauth2_refresh_tokens` r
                                 WHERE r.`access_token_id` = `oauth2_access_tokens`.`id`
                                   AND r.`revoked` = 0 AND r.`expires_at` > UNIX_TIMESTAMP()))',
            [$userId, $clientId]
        );
        return array_map('strval', $ids);
    }

    /** Revokes an access token and every refresh token issued with it. */
    public function revokeRefreshToken(string $refreshTokenId): void
    {
        \DBManager::get()->execute('UPDATE `oauth2_refresh_tokens` SET `revoked` = 1 WHERE `id` = ?', [$refreshTokenId]);
    }

    public function revokeLineageStep(string $accessTokenId, ?string $refreshTokenId = null): void
    {
        $db = \DBManager::get();
        // A token the core already revoked keeps its chdate, the time of its
        // rotation. MySQL assigns left to right, so chdate is set before revoked.
        $db->execute(
            'UPDATE `oauth2_access_tokens`
                SET `chdate` = IF(`revoked` = 1, `chdate`, UNIX_TIMESTAMP()), `revoked` = 1
              WHERE `id` = ?',
            [$accessTokenId]
        );
        $db->execute('UPDATE `oauth2_refresh_tokens` SET `revoked` = 1 WHERE `access_token_id` = ?', [$accessTokenId]);
        if ($refreshTokenId !== null) {
            $db->execute('UPDATE `oauth2_refresh_tokens` SET `revoked` = 1 WHERE `id` = ?', [$refreshTokenId]);
        }
    }
}
