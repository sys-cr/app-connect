<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

use AppConnect\Audit\EventLog;
use AppConnect\Runtime;
use AppConnect\WebLogin\DatabaseTicketStore;
use AppConnect\WebLogin\TicketGrant;
use AppConnect\WebLogin\WebLoginService;
use AppConnect\WebLogin\WebSignIn;

/**
 * Web login from the app (contract/web-login.md). "enter" is the address the
 * app opens; a script takes the ticket from the URL fragment, so it never
 * reaches the server in a URL, and posts it to "redeem". When the person is
 * signed in already, "redeem" only redirects; otherwise it asks "sign in as
 * …?" and "confirm" signs in. A link from someone else can therefore not
 * sign a browser into a foreign account unnoticed. Reachable without a session.
 */
class WebController extends PluginController
{
    /** Session key of a redeemed ticket that waits for the confirmation. */
    public const PENDING = 'app_connect_web_login_pending';
    /** How long the confirmation page stays valid. */
    public const CONFIRM_SECONDS = 120;

    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);
        $this->response->add_header('Cache-Control', 'no-store');
        $this->response->add_header('Referrer-Policy', 'no-referrer');
        $this->response->add_header('X-Frame-Options', 'DENY');
        $this->response->add_header('Content-Security-Policy', "frame-ancestors 'none'");
        PageLayout::setTitle(_('Anmeldung aus der App'));
        $this->login_url = URLHelper::getURL('dispatch.php/login');
    }

    public function enter_action()
    {
        $this->redeem_url = $this->url_for('web/redeem');
    }

    public function redeem_action()
    {
        if (!Request::isPost()) {
            $this->fail();
            return;
        }
        try {
            CSRFProtection::verifyUnsafeRequest();
        } catch (Exception $exception) {
            $this->fail();
            return;
        }

        $ticket = Request::get('ticket');
        $service = new WebLoginService(new DatabaseTicketStore());
        $grant = $service->redeem($ticket);
        if ($grant === null) {
            // Only a ticket that exists names a person; unknown values are not logged.
            $owner = $service->ownerOf($ticket);
            if ($owner !== null) {
                EventLog::recordWebLogin(EventLog::WEB_LOGIN_REJECT, $owner);
            }
            $this->fail();
            return;
        }

        $user = $this->allowedUser($grant);
        if ($user === null) {
            return;
        }

        $current = self::signedInPerson();
        if ($current !== null && $current->id === $user->id) {
            $this->seeOther(WebSignIn::target($grant->returnTo));
            return;
        }

        $_SESSION[self::PENDING] = [
            'user_id' => $grant->userId,
            'return_to' => $grant->returnTo,
            'access_token_id' => $grant->accessTokenId,
            'device_row_id' => $grant->deviceRowId,
            'expires_at' => time() + self::CONFIRM_SECONDS,
        ];
        $this->person = $user;
        $this->current_person = $current;
        $this->confirm_url = $this->url_for('web/confirm');
        $this->render_action('confirm');
    }

    /** Signs in after "sign in as …?" was answered; "cancel" drops the ticket. */
    public function confirm_action()
    {
        $pending = $_SESSION[self::PENDING] ?? null;
        unset($_SESSION[self::PENDING]);
        if (!Request::isPost()) {
            $this->fail();
            return;
        }
        try {
            CSRFProtection::verifyUnsafeRequest();
        } catch (Exception $exception) {
            $this->fail();
            return;
        }
        if (Request::submitted('cancel')) {
            $this->seeOther(Runtime::baseUrl() . '/');
            return;
        }
        $grant = self::pendingGrant($pending);
        if ($grant === null) {
            $this->fail();
            return;
        }
        $user = $this->allowedUser($grant);
        if ($user === null) {
            return;
        }
        $location = WebSignIn::signIn($user, $grant->returnTo);
        EventLog::recordWebLogin(EventLog::WEB_LOGIN_REDEEM, $user->id);
        $this->seeOther($location);
    }

    /** The person of the grant if they may sign in now; otherwise logs, fails and returns null. */
    private function allowedUser(TicketGrant $grant): ?User
    {
        $user = User::find($grant->userId);
        if (!$user instanceof User || !Runtime::settings()->isWebLoginEnabled()
            || !WebSignIn::isAllowed($user) || !WebSignIn::appSignInIsActive($grant)) {
            EventLog::recordWebLogin(EventLog::WEB_LOGIN_REJECT, $grant->userId);
            $this->fail();
            return null;
        }
        return $user;
    }

    private static function pendingGrant(mixed $pending): ?TicketGrant
    {
        if (!is_array($pending) || !is_string($pending['user_id'] ?? null)
            || !is_int($pending['expires_at'] ?? null) || $pending['expires_at'] < time()) {
            return null;
        }
        return new TicketGrant(
            $pending['user_id'],
            is_string($pending['return_to'] ?? null) ? $pending['return_to'] : null,
            is_string($pending['access_token_id'] ?? null) ? $pending['access_token_id'] : null,
            is_int($pending['device_row_id'] ?? null) ? $pending['device_row_id'] : null,
        );
    }

    private static function signedInPerson(): ?User
    {
        $current = $GLOBALS['user'] ?? null;
        $id = is_object($current) && isset($current->id) ? (string) $current->id : '';
        return $id !== '' && $id !== 'nobody' ? User::find($id) : null;
    }

    private function seeOther(string $location): void
    {
        $this->response->add_header('Location', $location)->set_status(303);
        $this->render_nothing();
    }

    /** The same answer for every failure: which check failed is not told. */
    private function fail(): void
    {
        $this->render_action('failed');
    }
}
