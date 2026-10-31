<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

use AppConnect\Devices\DeviceRecord;
use AppConnect\Devices\TokenLedger;
use AppConnect\Runtime;

/**
 * The Stud.IP side of a redeemed ticket: whether the person may sign in on
 * the web right now, whether the app sign-in behind the ticket still exists,
 * and the sign-in itself, done the way the core login controller does it.
 */
final class WebSignIn
{
    /**
     * The checks the core applies to a password login
     * (StudipAuthAbstract::CheckAuthentication, Authentication\Manager):
     * expired or locked account, e-mail activation pending, the IP range
     * check for admin and root, and the maintenance mode.
     */
    public static function isAllowed(\User $user): bool
    {
        if ($user->id === 'nobody' || (string) $user->username === '' || $user->locked || $user->isExpired()) {
            return false;
        }
        if ((string) $user->validation_key !== '') {
            return false;
        }
        $checkIpRange = (!empty($GLOBALS['ENABLE_ADMIN_IP_CHECK']) && $user->perms === 'admin')
            || (!empty($GLOBALS['ENABLE_ROOT_IP_CHECK']) && $user->perms === 'root');
        if ($checkIpRange && !\StudipAuthAbstract::CheckIPRange()) {
            return false;
        }
        return !\Config::get()->getValue('MAINTENANCE_MODE_ENABLE') || $user->perms === 'root';
    }

    /**
     * Whether the app sign-in the ticket came from is still connected. A
     * ticket from a device needs the device row, still active and with a
     * usable access token; one from an app sign-in without a device needs
     * that access token. A ticket requested with a password has neither.
     */
    public static function appSignInIsActive(TicketGrant $grant): bool
    {
        $ledger = new TokenLedger();
        if ($grant->deviceRowId !== null) {
            $row = DeviceRecord::findOneBySQL('`id` = ? AND `user_id` = ? AND `revoked_at` IS NULL', [
                $grant->deviceRowId,
                $grant->userId,
            ]);
            return $row !== null && !$ledger->isAccessTokenRevoked((string) $row->access_token_id);
        }
        if ($grant->accessTokenId !== null) {
            return !$ledger->isAccessTokenRevoked($grant->accessTokenId);
        }
        return true;
    }

    /**
     * Signs $user in on this browser session and returns the address to go
     * to. Another person's session is ended first: the session data is
     * cleared and the session id replaced, as in the core login.
     */
    public static function signIn(\User $user, ?string $returnTo): string
    {
        $current = $GLOBALS['user'] ?? null;
        if (is_object($current) && isset($current->id) && $current->id !== 'nobody' && $current->id !== $user->id
            && method_exists($current, 'set_last_action')) {
            // As on logout: the other person no longer counts as online.
            $current->set_last_action(strtotime('-15 minutes'));
        }

        // Nothing of the previous session's sign-in survives; the session
        // manager keeps 'auth' across regenerateId(), so it is emptied here.
        $_SESSION['auth'] = [];
        unset($_SESSION['phpCAS'], $_SESSION['redirect_after_login']);

        auth()->setAuthenticatedUser($user, ['auth_plugin' => 'standard']);
        if (class_exists(\Metrics::class)) {
            \Metrics::increment('app-connect.web-login.succeeded');
        }
        sess()->regenerateId(\Studip\Authentication\Manager::DEFAULT_KEPT_SESSION_VARIABLES);

        if ($returnTo !== null) {
            // The first request after a sign-in may jump to the personal
            // start page; the core skips that while redirect_after_login is
            // set and goes there instead.
            $_SESSION['redirect_after_login'] = $returnTo;
        }
        return self::target($returnTo);
    }

    /** The absolute address of the page to open: always on this instance. */
    public static function target(?string $returnTo): string
    {
        return ReturnPath::absoluteUrl(Runtime::baseUrl(), $returnTo ?? 'dispatch.php/start');
    }
}
