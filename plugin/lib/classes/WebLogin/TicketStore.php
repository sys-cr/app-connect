<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

/**
 * Storage of web-login tickets, keyed by the ticket hash. A row holds
 * user_id, return_to, access_token_id, device_row_id, created_at, expires_at
 * and used_at.
 */
interface TicketStore
{
    /** @param array{user_id: string, return_to: ?string, access_token_id: ?string, device_row_id: ?int, created_at: int, expires_at: int} $row */
    public function add(string $hash, array $row): void;

    public function countIssuedSince(string $userId, int $since): int;

    /**
     * Marks the ticket used if it is unused and expires after $now, in one
     * atomic step: of two concurrent claims at most one succeeds. Returns the
     * row on success, null otherwise.
     *
     * @return array<string, mixed>|null
     */
    public function claim(string $hash, int $now): ?array;

    /** The owner of a stored ticket, used or not; null when unknown. */
    public function ownerOf(string $hash): ?string;

    /** @return int number of deleted rows */
    public function deleteCreatedBefore(int $time): int;

    public function deleteForUser(string $userId): void;
}
