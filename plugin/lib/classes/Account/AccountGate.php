<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Account;

/**
 * Whether an account may use the app right now. Pairing, token refresh and
 * the web login all ask here, so a locked, expired or not yet activated
 * account can neither connect a device nor keep one running. These are the
 * account checks of the core password login (StudipAuthAbstract); the IP
 * range and the maintenance mode stay with the web login, because the app
 * is used outside the university network on purpose.
 */
final class AccountGate
{
    /** Roles that may use the app. */
    public const ROLES = ['autor', 'tutor', 'dozent', 'admin', 'root'];

    public static function mayUseApp(?\User $user): bool
    {
        return $user !== null && self::allows(
            (string) $user->id,
            (string) $user->username,
            (bool) $user->locked,
            $user->isExpired(),
            (string) $user->validation_key,
            (string) $user->perms
        );
    }

    /** The rule itself, on plain values. */
    public static function allows(
        string $userId,
        string $username,
        bool $locked,
        bool $expired,
        string $validationKey,
        string $perms
    ): bool {
        return $userId !== '' && $userId !== 'nobody' && $username !== ''
            && !$locked && !$expired && $validationKey === ''
            && in_array($perms, self::ROLES, true);
    }

    private function __construct()
    {
    }
}
