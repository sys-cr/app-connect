<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Integration;

use AppConnect\Devices\TokenLedger;

/**
 * The lock order of refresh and removal (device row, then refresh token):
 * whichever comes second sees what the first one did, so no valid token
 * family without a device is left. The steps mirror
 * LineageRefreshTokenGrant and DeviceService::remove with the real queries.
 */
final class LineageLockTest extends IntegrationTestCase
{
    private const USER = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    protected function setUp(): void
    {
        parent::setUp();
        $db = \DBManager::get();
        $db->execute("INSERT INTO `oauth2_access_tokens` (`id`, `user_id`, `client_id`, `expires_at`) VALUES ('a1', ?, 7, ?)", [self::USER, time() + 3600]);
        $db->execute("INSERT INTO `oauth2_refresh_tokens` (`id`, `access_token_id`, `expires_at`) VALUES ('r1', 'a1', ?)", [time() + 86400]);
        $db->execute(
            "INSERT INTO `app_connect_devices` (`device_id`, `user_id`, `name`, `platform`, `paired_via`, `access_token_id`,
                `refresh_token_id`, `bound_at`, `paired_at`, `mkdate`, `chdate`)
             VALUES ('00000000-0000-4000-8000-000000000001', ?, 'Phone', 'ios', 'pairing', 'a1', 'r1', 0, 0, 0, 0)",
            [self::USER]
        );
    }

    /** A refresh of r1 that holds its locks for $hold seconds before it commits. */
    private static function refresh(string $name, float $hold): string
    {
        $db = \DBManager::get();
        $ledger = new TokenLedger();
        $db->beginTransaction();
        $row = $ledger->lockDeviceRowByRefreshToken('r1');
        if ($ledger->lockRefreshTokenIsRevoked('r1')) {
            $db->rollBack();
            return 'refused';
        }
        $ledger->revokeLineageStep('a1', 'r1');
        $db->execute("INSERT INTO `oauth2_access_tokens` (`id`, `user_id`, `client_id`, `expires_at`) VALUES (?, ?, 7, ?)", ["a-$name", self::USER, time() + 3600]);
        $db->execute("INSERT INTO `oauth2_refresh_tokens` (`id`, `access_token_id`, `expires_at`) VALUES (?, ?, ?)", ["r-$name", "a-$name", time() + 86400]);
        if ($row !== null && $db->execute(
            'UPDATE `app_connect_devices` SET `access_token_id` = ?, `refresh_token_id` = ? WHERE `id` = ? AND `revoked_at` IS NULL',
            ["a-$name", "r-$name", $row]
        ) !== 1) {
            $db->rollBack();
            return 'refused';
        }
        usleep((int) ($hold * 1e6));
        $db->commit();
        return 'issued';
    }

    /** Removal of the device row that holds its locks for $hold seconds. */
    private static function remove(float $hold): string
    {
        $db = \DBManager::get();
        $ledger = new TokenLedger();
        $db->beginTransaction();
        if (!$ledger->lockDeviceRow(1, self::USER)) {
            $db->rollBack();
            return 'gone';
        }
        $row = $db->fetchOne('SELECT `access_token_id`, `refresh_token_id` FROM `app_connect_devices` WHERE `id` = 1');
        $ledger->lockRefreshTokenIsRevoked((string) $row['refresh_token_id']);
        $ledger->revokeLineageStep((string) $row['access_token_id'], (string) $row['refresh_token_id']);
        $db->execute('UPDATE `app_connect_devices` SET `revoked_at` = 1 WHERE `id` = 1');
        usleep((int) ($hold * 1e6));
        $db->commit();
        return 'removed';
    }

    /** @return list<string> */
    private static function validRefreshTokens(): array
    {
        return \DBManager::get()->fetchFirst('SELECT `id` FROM `oauth2_refresh_tokens` WHERE `revoked` = 0 ORDER BY `id`');
    }

    /**
     * The first process starts at once and holds its locks for a second; the
     * second starts 0.3 seconds later, while the first still holds them.
     *
     * @param \Closure(): string $first
     * @param \Closure(): string $second
     * @return list<string>
     */
    private function race(\Closure $first, \Closure $second): array
    {
        return $this->concurrently(2, static function (int $i) use ($first, $second): string {
            if ($i === 0) {
                return $first();
            }
            usleep(300000);
            return $second();
        });
    }

    public function testOfTwoSimultaneousRefreshesOnlyOneIssues(): void
    {
        $results = $this->race(static fn (): string => self::refresh('A', 1.0), static fn (): string => self::refresh('B', 0));

        self::assertSame(['issued', 'refused'], $results);
        self::assertSame(['r-A'], self::validRefreshTokens());
        self::assertSame('r-A', \DBManager::get()->fetchColumn('SELECT `refresh_token_id` FROM `app_connect_devices` WHERE `id` = 1'));
    }

    public function testARemovalDuringARefreshEndsTheNewPair(): void
    {
        $results = $this->race(static fn (): string => self::refresh('A', 1.0), static fn (): string => self::remove(0));

        self::assertSame(['issued', 'removed'], $results);
        self::assertSame([], self::validRefreshTokens());
    }

    public function testARefreshDuringARemovalIsRefused(): void
    {
        $results = $this->race(static fn (): string => self::remove(1.0), static fn (): string => self::refresh('A', 0));

        self::assertSame(['removed', 'refused'], $results);
        self::assertSame([], self::validRefreshTokens());
    }
}
