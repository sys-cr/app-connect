<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Pairing;

use AppConnect\Connect\PairingCode;
use AppConnect\Devices\DeviceRecord;

/**
 * Pairing codes of the profile page. A code is valid for five minutes and
 * once; only its hash is stored. A person has at most one open code.
 */
final class PairingService
{
    public const TTL_SECONDS = 300;
    public const MAX_PER_HOUR = 10;

    public const STATE_NONE = 'none';
    public const STATE_OPEN = 'open';
    public const STATE_EXPIRED = 'expired';
    public const STATE_CONNECTED = 'connected';

    /**
     * Creates a code for $userId and discards any open one. Returns null when
     * the hourly limit is reached.
     *
     * @return array{code: string, expires_at: int}|null
     */
    public function create(string $userId): ?array
    {
        $now = time();
        $recent = PairingCodeRecord::countBySQL('`user_id` = ? AND `mkdate` > ?', [$userId, $now - 3600]);
        if ($recent >= self::MAX_PER_HOUR) {
            return null;
        }
        $this->discard($userId);
        $code = PairingCode::generate();
        $row = new PairingCodeRecord(PairingCode::hash($code));
        $row->setData([
            'user_id' => $userId,
            'expires_at' => $now + self::TTL_SECONDS,
            'consumed_at' => null,
            'device_id' => null,
            'mkdate' => $now,
        ]);
        $row->store();
        return ['code' => $code, 'expires_at' => $now + self::TTL_SECONDS];
    }

    /**
     * Ends the open code of $userId. The row is kept (expired) so the hourly
     * limit still counts it.
     */
    public function discard(string $userId): void
    {
        \DBManager::get()->execute(
            'UPDATE `app_connect_pairing_codes` SET `expires_at` = ?
              WHERE `user_id` = ? AND `consumed_at` IS NULL AND `expires_at` > ?',
            [time(), $userId, time()]
        );
    }

    /**
     * Redeems a code atomically: of two concurrent requests only one updates
     * the row. Returns the owner of the code, or null when it is unknown,
     * expired or already used.
     */
    public function redeem(string $normalizedCode, string $deviceId): ?string
    {
        $hash = PairingCode::hash($normalizedCode);
        $now = time();
        $updated = \DBManager::get()->execute(
            'UPDATE `app_connect_pairing_codes` SET `consumed_at` = ?, `device_id` = ?
              WHERE `code_hash` = ? AND `consumed_at` IS NULL AND `expires_at` > ?',
            [$now, $deviceId, $hash, $now]
        );
        if ($updated !== 1) {
            return null;
        }
        $userId = \DBManager::get()->fetchColumn(
            'SELECT `user_id` FROM `app_connect_pairing_codes` WHERE `code_hash` = ?',
            [$hash]
        );
        return is_string($userId) ? $userId : null;
    }

    /**
     * State of the latest code of $userId for the live status on the profile
     * page. A redeemed code reports the device it connected.
     *
     * @return array{state: string, expires_at?: int, device?: DeviceRecord}
     */
    public function status(string $userId): array
    {
        $latest = PairingCodeRecord::findOneBySQL('`user_id` = ? ORDER BY `mkdate` DESC LIMIT 1', [$userId]);
        if ($latest === null) {
            return ['state' => self::STATE_NONE];
        }
        if ($latest->consumed_at !== null) {
            $device = DeviceRecord::findOneBySQL(
                '`user_id` = ? AND `device_id` = ? AND `paired_via` = ? ORDER BY `paired_at` DESC LIMIT 1',
                [$userId, $latest->device_id, 'pairing']
            );
            return $device !== null
                ? ['state' => self::STATE_CONNECTED, 'device' => $device]
                : ['state' => self::STATE_EXPIRED];
        }
        if ((int) $latest->expires_at <= time()) {
            return ['state' => self::STATE_EXPIRED];
        }
        return ['state' => self::STATE_OPEN, 'expires_at' => (int) $latest->expires_at];
    }

    /** Deletes codes that expired more than a day ago. */
    public function purge(): void
    {
        \DBManager::get()->execute(
            'DELETE FROM `app_connect_pairing_codes` WHERE `expires_at` < ?',
            [time() - 86400]
        );
    }
}
