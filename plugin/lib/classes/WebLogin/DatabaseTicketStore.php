<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

/**
 * Tickets in `app_connect_web_login_tickets` (migration 009).
 */
final class DatabaseTicketStore implements TicketStore
{
    public function add(string $hash, array $row): void
    {
        \DBManager::get()->execute(
            'INSERT INTO `app_connect_web_login_tickets`
                (`ticket_hash`, `user_id`, `return_to`, `access_token_id`, `device_row_id`, `created_at`, `expires_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $hash, $row['user_id'], $row['return_to'], $row['access_token_id'], $row['device_row_id'],
                $row['created_at'], $row['expires_at'],
            ]
        );
    }

    public function countIssuedSince(string $userId, int $since): int
    {
        return (int) \DBManager::get()->fetchColumn(
            'SELECT COUNT(*) FROM `app_connect_web_login_tickets` WHERE `user_id` = ? AND `created_at` > ?',
            [$userId, $since]
        );
    }

    public function claim(string $hash, int $now): ?array
    {
        // The condition sits in the UPDATE itself, so the database decides
        // which of two concurrent requests gets the ticket.
        $updated = \DBManager::get()->execute(
            'UPDATE `app_connect_web_login_tickets` SET `used_at` = ?
              WHERE `ticket_hash` = ? AND `used_at` IS NULL AND `expires_at` > ?',
            [$now, $hash, $now]
        );
        if ($updated !== 1) {
            return null;
        }
        $row = \DBManager::get()->fetchOne(
            'SELECT `user_id`, `return_to`, `access_token_id`, `device_row_id`, `created_at`, `expires_at`, `used_at`
               FROM `app_connect_web_login_tickets` WHERE `ticket_hash` = ?',
            [$hash]
        );
        return is_array($row) && $row !== [] ? $row : null;
    }

    public function ownerOf(string $hash): ?string
    {
        $userId = \DBManager::get()->fetchColumn(
            'SELECT `user_id` FROM `app_connect_web_login_tickets` WHERE `ticket_hash` = ?',
            [$hash]
        );
        return is_string($userId) ? $userId : null;
    }

    public function deleteCreatedBefore(int $time): int
    {
        return (int) \DBManager::get()->execute(
            'DELETE FROM `app_connect_web_login_tickets` WHERE `created_at` < ?',
            [$time]
        );
    }

    public function deleteForUser(string $userId): void
    {
        \DBManager::get()->execute('DELETE FROM `app_connect_web_login_tickets` WHERE `user_id` = ?', [$userId]);
    }
}
