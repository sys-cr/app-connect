<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Devices;

/**
 * The lineage of a device's tokens during a refresh at the plugin's token
 * endpoint: lock it, move the binding to the new pair, or end it when the
 * core revoked its access token. The connected devices themselves are
 * managed by {@see DeviceService}.
 */
final class TokenLineage
{
    public function __construct(private readonly TokenLedger $ledger)
    {
    }

    /**
     * Locks the lineage step of a refresh, inside the refresh's transaction:
     * first the device row bound to $refreshTokenId (if any), then the
     * refresh token row. Removal and revocation lock in the same order, so a
     * refresh either finishes before them or sees their result. Returns the
     * id of the bound device row, null when the token belongs to no device.
     *
     * @throws LineageEnded when the refresh token was revoked meanwhile
     */
    public function lock(string $refreshTokenId): ?int
    {
        $rowId = $this->ledger->lockDeviceRowByRefreshToken($refreshTokenId);
        if ($this->ledger->lockRefreshTokenIsRevoked($refreshTokenId)) {
            throw new LineageEnded();
        }
        return $rowId;
    }

    /**
     * Moves the binding of the locked device row $rowId to the new pair.
     *
     * @throws LineageEnded when the row is no longer active, so the new pair
     *                      must not be handed out
     */
    public function rebind(int $rowId, string $accessTokenId, ?string $refreshTokenId): void
    {
        $row = DeviceRecord::findOneBySQL('`id` = ? AND `revoked_at` IS NULL', [$rowId]);
        if ($row === null) {
            throw new LineageEnded();
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
    public function endRevoked(string $refreshTokenId): void
    {
        $this->ledger->revokeRefreshToken($refreshTokenId);
        $row = DeviceRecord::findOneBySQL('`refresh_token_id` = ? AND `revoked_at` IS NULL', [$refreshTokenId]);
        if ($row !== null) {
            $row->revoked_at = time();
            $row->store();
        }
    }
}
