<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Integration;

use AppConnect\Devices\TokenLedger;

/** The queries on the core's OAuth2 tables. */
final class TokenLedgerTest extends IntegrationTestCase
{
    private const USER = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    private function token(string $id, int $expiresAt, int $revoked = 0, ?int $refreshExpiresAt = null): void
    {
        $db = \DBManager::get();
        $db->execute(
            'INSERT INTO `oauth2_access_tokens` (`id`, `user_id`, `client_id`, `revoked`, `expires_at`, `mkdate`, `chdate`)
             VALUES (?, ?, 7, ?, ?, 100, 100)',
            [$id, self::USER, $revoked, $expiresAt]
        );
        if ($refreshExpiresAt !== null) {
            $db->execute(
                'INSERT INTO `oauth2_refresh_tokens` (`id`, `access_token_id`, `revoked`, `expires_at`) VALUES (?, ?, 0, ?)',
                ['r-' . $id, $id, $refreshExpiresAt]
            );
        }
    }

    public function testValidTokensIncludeExpiredAccessTokensWithAValidRefreshToken(): void
    {
        $this->token('live', time() + 3600);
        $this->token('renewable', time() - 10, 0, time() + 86400);
        $this->token('gone', time() - 10, 0, time() - 5);
        $this->token('revoked', time() + 3600, 1);

        $valid = (new TokenLedger())->validAccessTokens(self::USER, '7');
        sort($valid);

        self::assertSame(['live', 'renewable'], $valid);
        self::assertSame('r-renewable', (new TokenLedger())->refreshTokenFor('renewable'));
    }

    public function testRevokingAStepRevokesBothTokens(): void
    {
        $this->token('t1', time() + 3600, 0, time() + 86400);

        (new TokenLedger())->revokeLineageStep('t1', 'r-t1');

        self::assertTrue((new TokenLedger())->isAccessTokenRevoked('t1'));
        self::assertTrue((new TokenLedger())->isRefreshTokenRevoked('r-t1'));
    }

    /** A token the core revoked already keeps its chdate, the time of its rotation. */
    public function testRevokingKeepsTheChangeDateOfARevokedToken(): void
    {
        $this->token('done', time() + 3600, 1);
        $this->token('open', time() + 3600);

        (new TokenLedger())->revokeLineageStep('done');
        (new TokenLedger())->revokeLineageStep('open');

        $chdate = static fn (string $id): int => (int) \DBManager::get()->fetchColumn(
            'SELECT `chdate` FROM `oauth2_access_tokens` WHERE `id` = ?',
            [$id]
        );
        self::assertSame(100, $chdate('done'));
        self::assertGreaterThan(100, $chdate('open'));
    }
}
