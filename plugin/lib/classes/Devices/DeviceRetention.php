<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Devices;

use AppConnect\WebLogin\DatabaseTicketStore;

/**
 * Retention of device rows. A row is kept while its token lineage can still
 * be used, and one month beyond: removed devices stay listed in the audit
 * trail for a while, active ones outlive a short gap in use. Rows of deleted
 * accounts go at once.
 */
final class DeviceRetention
{
    public const GRACE_SECONDS = 30 * 86400;

    /**
     * Deletes rows whose lineage ended more than a month ago. The end is the
     * expiry of the bound refresh token, else of the access token (browser
     * sign-ins without a refresh token), else the removal time; a row with
     * none of these has no lineage left and goes as well.
     *
     * @return int number of deleted rows
     */
    public function purge(int $now): int
    {
        return (int) \DBManager::get()->execute(
            'DELETE `d` FROM `app_connect_devices` `d`
               LEFT JOIN `oauth2_refresh_tokens` `r` ON `r`.`id` = `d`.`refresh_token_id`
               LEFT JOIN `oauth2_access_tokens` `a` ON `a`.`id` = `d`.`access_token_id`
              WHERE COALESCE(`r`.`expires_at`, `a`.`expires_at`, `d`.`revoked_at`, 0) < ?',
            [$now - self::GRACE_SECONDS]
        );
    }

    /** Deletes everything app-connect keeps about a deleted account. */
    public function forgetUser(string $userId): void
    {
        $db = \DBManager::get();
        $db->execute('DELETE FROM `app_connect_devices` WHERE `user_id` = ?', [$userId]);
        $db->execute('DELETE FROM `app_connect_pairing_codes` WHERE `user_id` = ?', [$userId]);
        (new DatabaseTicketStore())->deleteForUser($userId);
    }
}
