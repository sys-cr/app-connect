<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Audit;

/**
 * Entries in the Stud.IP event log for operator actions. The actions are
 * registered by the plugin migrations; entries carry no personal data beyond
 * the acting account the event log records anyway.
 */
final class EventLog
{
    public const ENABLE = 'APPCONNECT_ENABLE';
    public const DISABLE = 'APPCONNECT_DISABLE';
    public const CLIENT_CREATE = 'APPCONNECT_CLIENT_CREATE';
    public const CLIENT_REUSE = 'APPCONNECT_CLIENT_REUSE';
    public const SETTINGS_CHANGE = 'APPCONNECT_SETTINGS';
    public const WEB_LOGIN_ISSUE = 'APPCONNECT_WEB_LOGIN_ISSUE';
    public const WEB_LOGIN_REDEEM = 'APPCONNECT_WEB_LOGIN_REDEEM';
    public const WEB_LOGIN_REJECT = 'APPCONNECT_WEB_LOGIN_REJECT';
    public const ICAL_KEY_CREATE = 'APPCONNECT_ICAL_KEY_CREATE';
    public const ICAL_KEY_RESET = 'APPCONNECT_ICAL_KEY_RESET';

    /** Actions about a web login from the app; they carry the person, never the ticket. */
    public const WEB_LOGIN_ACTIONS = [self::WEB_LOGIN_ISSUE, self::WEB_LOGIN_REDEEM, self::WEB_LOGIN_REJECT];

    /**
     * Actions about the calendar key. It is a standing read access to the
     * person's calendar, so handing one out and dropping one are both worth
     * a line – and like the web login they name the person, never the key.
     */
    public const ICAL_KEY_ACTIONS = [self::ICAL_KEY_CREATE, self::ICAL_KEY_RESET];

    /** The core deletes web-login entries after this time (`log_actions.expires`). */
    public const WEB_LOGIN_RETENTION_SECONDS = 90 * 86400;

    /**
     * Action name => [description (max. 64 characters), info template].
     */
    public const ACTIONS = [
        self::ENABLE => ['App-Anbindung eingeschaltet', '%user schaltet die App-Anbindung ein.'],
        self::DISABLE => ['App-Anbindung ausgeschaltet', '%user schaltet die App-Anbindung aus.'],
        self::CLIENT_CREATE => ['OAuth2-Client der App angelegt', '%user legt den OAuth2-Client der App an (%info).'],
        self::CLIENT_REUSE => ['OAuth2-Client der App wiederverwendet', '%user verwendet den OAuth2-Client der App wieder (%info).'],
        self::SETTINGS_CHANGE => ['Einstellungen der App-Anbindung geändert', '%user ändert Einstellungen der App-Anbindung (%info).'],
        self::WEB_LOGIN_ISSUE => ['Web-Anmeldung aus der App angefordert', '%user fordert aus der App eine Anmeldung im Web an.'],
        self::WEB_LOGIN_REDEEM => ['Web-Anmeldung aus der App eingelöst', '%user meldet sich aus der App im Web an.'],
        self::WEB_LOGIN_REJECT => ['Web-Anmeldung aus der App abgelehnt', 'Eine Anmeldung von %user aus der App im Web wird abgelehnt.'],
        self::ICAL_KEY_CREATE => ['Kalender-Schlüssel aus der App erzeugt', '%user lässt aus der App einen Kalender-Schlüssel erzeugen.'],
        self::ICAL_KEY_RESET => ['Kalender-Schlüssel aus der App zurückgesetzt', '%user setzt aus der App den Kalender-Schlüssel zurück.'],
    ];

    /** Writes one entry; $info is a short, non-personal note such as a client id. */
    public static function record(string $action, ?string $info = null): void
    {
        \StudipLog::log($action, null, null, $info);
    }

    /** Writes one entry with $userId as the acting and affected person. */
    public static function recordForUser(string $action, string $userId): void
    {
        \StudipLog::log($action, $userId, null, null, null, $userId);
    }

    /** Writes one web-login entry with $userId as the acting and affected person. */
    public static function recordWebLogin(string $action, string $userId): void
    {
        self::recordForUser($action, $userId);
    }
}
