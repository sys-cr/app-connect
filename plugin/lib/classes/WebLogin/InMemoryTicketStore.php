<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

/**
 * Ticket store for the tests, with the semantics of {@see DatabaseTicketStore}.
 */
final class InMemoryTicketStore implements TicketStore
{
    /** @var array<string, array<string, mixed>> */
    public array $rows = [];

    public function add(string $hash, array $row): void
    {
        $this->rows[$hash] = $row + ['used_at' => null];
    }

    public function countIssuedSince(string $userId, int $since): int
    {
        return count(array_filter(
            $this->rows,
            static fn (array $row): bool => $row['user_id'] === $userId && $row['created_at'] > $since
        ));
    }

    public function claim(string $hash, int $now): ?array
    {
        $row = $this->rows[$hash] ?? null;
        if ($row === null || $row['used_at'] !== null || $row['expires_at'] <= $now) {
            return null;
        }
        $this->rows[$hash]['used_at'] = $now;
        return $this->rows[$hash];
    }

    public function ownerOf(string $hash): ?string
    {
        return isset($this->rows[$hash]) ? (string) $this->rows[$hash]['user_id'] : null;
    }

    public function deleteCreatedBefore(int $time): int
    {
        $before = count($this->rows);
        $this->rows = array_filter($this->rows, static fn (array $row): bool => $row['created_at'] >= $time);
        return $before - count($this->rows);
    }

    public function deleteForUser(string $userId): void
    {
        $this->rows = array_filter($this->rows, static fn (array $row): bool => $row['user_id'] !== $userId);
    }
}
