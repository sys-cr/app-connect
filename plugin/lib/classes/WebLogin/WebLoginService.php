<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

/**
 * Issues and redeems web-login tickets. A ticket is valid for
 * {@see TTL_SECONDS} and once; a person gets at most {@see MAX_PER_WINDOW}
 * tickets in {@see WINDOW_SECONDS}. Only the hash of a ticket is stored.
 */
final class WebLoginService
{
    public const TTL_SECONDS = 60;
    public const MAX_PER_WINDOW = 10;
    public const WINDOW_SECONDS = 300;
    public const RETENTION_SECONDS = 86400;

    public function __construct(private readonly TicketStore $store, private readonly ?\Closure $clock = null)
    {
    }

    /**
     * A new ticket for $userId, or null when the person asked too often.
     * $returnTo must already have passed {@see ReturnPath::parse()}.
     */
    public function issue(string $userId, ?string $returnTo, ?string $accessTokenId, ?int $deviceRowId): ?string
    {
        if ($returnTo !== null && ReturnPath::parse($returnTo) !== $returnTo) {
            throw new \InvalidArgumentException('Invalid return path');
        }
        $now = $this->now();
        if ($this->store->countIssuedSince($userId, $now - self::WINDOW_SECONDS) >= self::MAX_PER_WINDOW) {
            return null;
        }
        $ticket = WebLoginTicket::generate();
        $this->store->add(WebLoginTicket::hash($ticket), [
            'user_id' => $userId,
            'return_to' => $returnTo,
            'access_token_id' => $accessTokenId,
            'device_row_id' => $deviceRowId,
            'created_at' => $now,
            'expires_at' => $now + self::TTL_SECONDS,
        ]);
        return $ticket;
    }

    /**
     * Claims a ticket. Null when it is malformed, unknown, expired or used;
     * a successful claim uses the ticket up, whatever the caller does next.
     */
    public function redeem(mixed $ticket): ?TicketGrant
    {
        if (!WebLoginTicket::isWellFormed($ticket)) {
            return null;
        }
        $row = $this->store->claim(WebLoginTicket::hash($ticket), $this->now());
        if ($row === null) {
            return null;
        }
        $returnTo = $row['return_to'] ?? null;
        $deviceRowId = $row['device_row_id'] ?? null;
        $accessTokenId = $row['access_token_id'] ?? null;
        return new TicketGrant(
            (string) $row['user_id'],
            // Checked again: a stored path that no longer passes is dropped.
            is_string($returnTo) ? ReturnPath::parse($returnTo) : null,
            is_scalar($accessTokenId) && (string) $accessTokenId !== '' ? (string) $accessTokenId : null,
            is_numeric($deviceRowId) ? (int) $deviceRowId : null,
        );
    }

    /** The owner of a ticket that exists but could not be claimed, for the event log. */
    public function ownerOf(mixed $ticket): ?string
    {
        return WebLoginTicket::isWellFormed($ticket) ? $this->store->ownerOf(WebLoginTicket::hash($ticket)) : null;
    }

    /** Deletes tickets issued more than a day ago. */
    public function purge(): int
    {
        return $this->store->deleteCreatedBefore($this->now() - self::RETENTION_SECONDS);
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
