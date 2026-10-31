<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Devices;

/**
 * Connected devices and their token lineages. Every operation is limited to
 * one user; revoking one device never touches the lineage of another.
 */
final class DeviceService
{
    public const VIA_PAIRING = 'pairing';
    public const VIA_BROWSER = 'browser';

    /** Upper bound when following a lineage refreshed outside the plugin. */
    private const MAX_FOLLOW_STEPS = 50;

    public function __construct(private readonly TokenLedger $ledger, private readonly string $clientId)
    {
    }

    /**
     * Records a new connection. An active row of the same user and device id
     * is revoked first, so reconnecting an installation replaces it.
     */
    public function connect(
        string $userId,
        DeviceInput $device,
        string $via,
        string $accessTokenId,
        ?string $refreshTokenId
    ): DeviceRecord {
        foreach ($this->activeRows($userId, $device->deviceId) as $previous) {
            $this->revoke($previous);
        }
        $now = time();
        $row = new DeviceRecord();
        $row->setData([
            'device_id' => $device->deviceId,
            'user_id' => $userId,
            'name' => $device->name,
            'platform' => $device->platform,
            'paired_via' => $via,
            'access_token_id' => $accessTokenId,
            'refresh_token_id' => $refreshTokenId,
            'bound_at' => $now,
            'paired_at' => $now,
            'revoked_at' => null,
        ]);
        $row->store();
        return $row;
    }

    /**
     * Registration after a browser sign-in: binds the lineage of the calling
     * access token. Returns null when that token is already bound.
     */
    public function registerBrowserSignIn(string $userId, string $accessTokenId, DeviceInput $device): ?DeviceRecord
    {
        if (DeviceRecord::countBySQL('`access_token_id` = ?', [$accessTokenId]) > 0) {
            return null;
        }
        $refreshTokenId = $this->ledger->refreshTokenFor($accessTokenId);
        if ($refreshTokenId !== null && DeviceRecord::countBySQL('`refresh_token_id` = ?', [$refreshTokenId]) > 0) {
            return null;
        }
        return $this->connect($userId, $device, self::VIA_BROWSER, $accessTokenId, $refreshTokenId);
    }

    /** Moves the binding of the device holding $oldRefreshTokenId to the new pair. */
    public function rebind(string $oldRefreshTokenId, string $accessTokenId, ?string $refreshTokenId): void
    {
        $row = DeviceRecord::findOneBySQL('`refresh_token_id` = ? AND `revoked_at` IS NULL', [$oldRefreshTokenId]);
        if ($row === null) {
            return;
        }
        $row->access_token_id = $accessTokenId;
        $row->refresh_token_id = $refreshTokenId;
        $row->bound_at = time();
        $row->store();
    }

    public function isAccessTokenRevoked(string $accessTokenId): bool
    {
        return $this->ledger->isAccessTokenRevoked($accessTokenId);
    }

    /**
     * Ends a lineage whose access token was revoked on the core page: revokes
     * the refresh token and marks the bound device, if any, as removed.
     */
    public function endRevokedLineage(string $refreshTokenId): void
    {
        $this->ledger->revokeRefreshToken($refreshTokenId);
        $row = DeviceRecord::findOneBySQL('`refresh_token_id` = ? AND `revoked_at` IS NULL', [$refreshTokenId]);
        if ($row !== null) {
            $row->revoked_at = time();
            $row->store();
        }
    }

    /** @return list<DeviceRecord> active devices, newest first */
    public function activeDevices(string $userId): array
    {
        return DeviceRecord::findBySQL('`user_id` = ? AND `revoked_at` IS NULL ORDER BY `paired_at` DESC', [$userId]);
    }

    /** Removes one of the user's own devices. False when it is not theirs or already removed. */
    public function remove(string $userId, int $rowId): bool
    {
        $row = DeviceRecord::findOneBySQL('`id` = ? AND `user_id` = ? AND `revoked_at` IS NULL', [$rowId, $userId]);
        if ($row === null) {
            return false;
        }
        $this->revoke($row);
        return true;
    }

    /**
     * Sign-ins of the app client that belong to no active device, e.g. after
     * an interrupted registration.
     *
     * @return list<string> access token ids
     */
    public function unassignedTokens(string $userId): array
    {
        $bound = [];
        foreach ($this->activeDevices($userId) as $row) {
            $bound[] = (string) $row->access_token_id;
        }
        return array_values(array_diff($this->ledger->validAccessTokens($userId, $this->clientId), $bound));
    }

    /** Ends every sign-in of the app client that belongs to no active device. */
    public function endUnassigned(string $userId): int
    {
        $tokens = $this->unassignedTokens($userId);
        foreach ($tokens as $accessTokenId) {
            $this->ledger->revokeLineageStep($accessTokenId);
        }
        return count($tokens);
    }

    /**
     * Revokes the bound pair. If the lineage was refreshed at the core token
     * endpoint (bound refresh token revoked, row still active), the successors
     * are found by time and revoked too – more rather than fewer.
     */
    private function revoke(DeviceRecord $row): void
    {
        $accessTokenId = (string) $row->access_token_id;
        $refreshTokenId = $row->refresh_token_id !== null ? (string) $row->refresh_token_id : null;
        $broken = $refreshTokenId !== null && $this->ledger->isRefreshTokenRevoked($refreshTokenId);

        // Successors are found by the rotation time of the bound token, so
        // they are looked up before the bound token is touched.
        if ($broken) {
            $this->revokeSuccessors((string) $row->user_id, $accessTokenId);
        }
        $this->ledger->revokeLineageStep($accessTokenId, $refreshTokenId);
        $row->revoked_at = time();
        $row->store();
    }

    private function revokeSuccessors(string $userId, string $accessTokenId): void
    {
        $bound = [];
        foreach (DeviceRecord::findBySQL('`user_id` = ? AND `revoked_at` IS NULL', [$userId]) as $other) {
            $bound[] = (string) $other->access_token_id;
        }
        $queue = [$accessTokenId];
        $seen = [$accessTokenId => true];
        for ($step = 0; $queue !== [] && $step < self::MAX_FOLLOW_STEPS; $step++) {
            $current = array_shift($queue);
            $changedAt = $this->ledger->accessTokenChangedAt($current);
            if ($changedAt === null) {
                continue;
            }
            $exclude = array_merge($bound, array_keys($seen));
            foreach ($this->ledger->accessTokensIssuedAround($userId, $this->clientId, $changedAt, $exclude) as $successor) {
                // Its own rotation time is read before anything is revoked.
                $seen[$successor] = true;
                $queue[] = $successor;
            }
        }
        foreach (array_keys($seen) as $id) {
            if ($id !== $accessTokenId) {
                $this->ledger->revokeLineageStep($id);
            }
        }
    }

    /** @return list<DeviceRecord> */
    private function activeRows(string $userId, string $deviceId): array
    {
        return DeviceRecord::findBySQL(
            '`user_id` = ? AND `device_id` = ? AND `revoked_at` IS NULL',
            [$userId, $deviceId]
        );
    }
}
