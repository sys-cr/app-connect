<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Settings;

/**
 * The operator settings as Stud.IP configuration entries in the section
 * "Stud.App" of Admin → System → Konfiguration. Root edits them with the
 * core editor; the plugin reads them through {@see ConfigBackedSettingsStore}.
 */
final class OperatorConfig
{
    public const SECTION = 'Stud.App';

    public const ENABLED = 'APP_CONNECT_ENABLED';
    public const PAIRING_ENABLED = 'APP_CONNECT_PAIRING_ENABLED';
    public const PROFILE_ENTRY = 'APP_CONNECT_PROFILE_ENTRY';
    public const INSTANCE_NAME = 'APP_CONNECT_INSTANCE_NAME';
    public const INSTANCE_SHORT_NAME = 'APP_CONNECT_INSTANCE_SHORT_NAME';
    public const PRIVACY_POLICY_URL = 'APP_CONNECT_PRIVACY_POLICY_URL';
    public const STORE_APP_STORE = 'APP_CONNECT_STORE_APP_STORE';
    public const STORE_GOOGLE_PLAY = 'APP_CONNECT_STORE_GOOGLE_PLAY';
    public const FEATURE_COURSE_CATALOGUE = 'APP_CONNECT_FEATURE_COURSE_CATALOGUE';
    public const FEATURE_BLUBBER = 'APP_CONNECT_FEATURE_BLUBBER';
    public const FEATURE_MENSA = 'APP_CONNECT_FEATURE_MENSA';
    public const ROOM_FINDER_PROPERTY = 'APP_CONNECT_ROOM_FINDER_PROPERTY';
    public const FEATURE_MESSAGES = 'APP_CONNECT_FEATURE_MESSAGES';
    public const FEATURE_EVENT_PLACES = 'APP_CONNECT_FEATURE_EVENT_PLACES';
    public const FEATURE_COURSEWARE = 'APP_CONNECT_FEATURE_COURSEWARE';
    public const FEATURE_WEB_LOGIN = 'APP_CONNECT_FEATURE_WEB_LOGIN';
    public const FEATURE_ICAL_KEY = 'APP_CONNECT_FEATURE_ICAL_KEY';
    public const SUPPORT_EMAIL = 'APP_CONNECT_SUPPORT_EMAIL';
    public const LOGO_URL = 'APP_CONNECT_LOGO_URL';
    public const ACCENT_COLOR = 'APP_CONNECT_ACCENT_COLOR';
    public const LINK_HOMEPAGE = 'APP_CONNECT_LINK_HOMEPAGE';
    public const LINK_CAMPUS_MANAGEMENT = 'APP_CONNECT_LINK_CAMPUS_MANAGEMENT';
    public const LINK_LIBRARY = 'APP_CONNECT_LINK_LIBRARY';
    public const LINK_IT_HELP = 'APP_CONNECT_LINK_IT_HELP';
    public const MENSA_OPENMENSA_IDS = 'APP_CONNECT_MENSA_OPENMENSA_IDS';
    public const MENSA_URL = 'APP_CONNECT_MENSA_URL';

    /** Optional app feature id → configuration field. */
    public const FEATURE_FIELDS = [
        'courseCatalogue' => self::FEATURE_COURSE_CATALOGUE,
        'blubber' => self::FEATURE_BLUBBER,
        'mensa' => self::FEATURE_MENSA,
        'messages' => self::FEATURE_MESSAGES,
        'eventPlaces' => self::FEATURE_EVENT_PLACES,
        'courseware' => self::FEATURE_COURSEWARE,
        'webLogin' => self::FEATURE_WEB_LOGIN,
        'icalKey' => self::FEATURE_ICAL_KEY,
    ];

    /** External link type of the discovery → configuration field. */
    public const LINK_FIELDS = [
        'homepage' => self::LINK_HOMEPAGE,
        'campusManagement' => self::LINK_CAMPUS_MANAGEMENT,
        'library' => self::LINK_LIBRARY,
        'itHelp' => self::LINK_IT_HELP,
    ];

    /** Store link key → configuration field. */
    public const STORE_FIELDS = [
        'appStore' => self::STORE_APP_STORE,
        'googlePlay' => self::STORE_GOOGLE_PLAY,
    ];

    /** Fields whose value must be an https address. */
    public const URL_FIELDS = [
        self::PRIVACY_POLICY_URL, self::STORE_APP_STORE, self::STORE_GOOGLE_PLAY, self::LOGO_URL,
        self::LINK_HOMEPAGE, self::LINK_CAMPUS_MANAGEMENT, self::LINK_LIBRARY, self::LINK_IT_HELP, self::MENSA_URL,
    ];

    /**
     * Definition of every entry: type, default and the description shown in
     * the configuration list (at most 255 characters, the column size).
     *
     * @return array<string, array{type: string, value: mixed, description: string}>
     */
    public static function definitions(): array
    {
        return [
            self::ENABLED => ['type' => 'boolean', 'value' => false, 'description' =>
                'Schaltet die Anbindung der Stud.App ein. Beim Einschalten legt das Plugin einen öffentlichen OAuth2-Client an oder verwendet den bisherigen wieder; beim Ausschalten wird er widerrufen, angemeldete Apps verlieren den Zugang.'],
            self::PAIRING_ENABLED => ['type' => 'boolean', 'value' => false, 'description' =>
                'Erlaubt das Verbinden per QR-Code aus dem Profil (Kopplungscode, fünf Minuten gültig, einmalig). Aus: Die Apps verbinden sich nur über die Anmeldung im Browser.'],
            self::PROFILE_ENTRY => ['type' => 'boolean', 'value' => true, 'description' =>
                'Zeigt unter Profil → Einstellungen den Eintrag „Stud.App Verbindung“ (QR-Code, verbundene Geräte).'],
            self::INSTANCE_NAME => ['type' => 'string', 'value' => '', 'description' =>
                'Name dieser Stud.IP-Installation in der App. Leer: der Name der Installation.'],
            self::INSTANCE_SHORT_NAME => ['type' => 'string', 'value' => '', 'description' =>
                'Kurzname dieser Stud.IP-Installation in der App (z. B. für Widgets).'],
            self::PRIVACY_POLICY_URL => ['type' => 'string', 'value' => '', 'description' =>
                'Adresse der Datenschutzerklärung der Hochschule, beginnend mit https://. Andere Werte werden nicht verwendet.'],
            self::STORE_APP_STORE => ['type' => 'string', 'value' => '', 'description' =>
                'Link zur Stud.App im App Store für die Seite „App holen“, beginnend mit https://.'],
            self::STORE_GOOGLE_PLAY => ['type' => 'string', 'value' => '', 'description' =>
                'Link zur Stud.App bei Google Play für die Seite „App holen“, beginnend mit https://.'],
            self::FEATURE_COURSE_CATALOGUE => ['type' => 'boolean', 'value' => false, 'description' =>
                'Zeigt in der App die Veranstaltungssuche und das Vorlesungsverzeichnis.'],
            self::FEATURE_BLUBBER => ['type' => 'boolean', 'value' => false, 'description' =>
                'Zeigt in der App den Blubber-Chat.'],
            self::FEATURE_MENSA => ['type' => 'boolean', 'value' => false, 'description' =>
                'Zeigt in der App den Mensa-Speiseplan (braucht eine Mensa-Datenquelle).'],
            self::FEATURE_MESSAGES => ['type' => 'boolean', 'value' => true, 'description' =>
                'Zeigt in der App die Nachrichten.'],
            self::FEATURE_EVENT_PLACES => ['type' => 'boolean', 'value' => true, 'description' =>
                'Zeigt in der App bei Terminen den gebuchten Raum mit Karte und Raumfinder (Raumverwaltung von Stud.IP).'],
            self::FEATURE_COURSEWARE => ['type' => 'boolean', 'value' => true, 'description' =>
                'Zeigt in der App die Courseware der Veranstaltungen zum Lesen. Wirkt nur, wenn das Modul Courseware aktiv ist.'],
            self::FEATURE_WEB_LOGIN => ['type' => 'boolean', 'value' => true, 'description' =>
                'Öffnet Stud.IP-Seiten aus der App ohne erneute Anmeldung im Browser (einmaliger Link, 60 Sekunden gültig). Ein zweiter Faktor des Identity Providers wird dabei nicht abgefragt.'],
            self::FEATURE_ICAL_KEY => ['type' => 'boolean', 'value' => true, 'description' =>
                'Kalender aus der App abonnieren: die App holt den iCal-Schlüssel der Person und übergibt die webcal-Adresse an den Systemkalender. Wer den Schlüssel kennt, kann den Kalender lesen. Ausgeschaltet bleibt der Weg über die Kalender-Seite im Browser.'],
            self::SUPPORT_EMAIL => ['type' => 'string', 'value' => '', 'description' =>
                'E-Mail-Adresse des Supports der Hochschule; die App bietet sie für Hilfe und zum Melden von Inhalten an. Leer: kein Support-Kontakt der Hochschule in der App.'],
            self::LOGO_URL => ['type' => 'string', 'value' => '', 'description' =>
                'Adresse des Hochschul-Logos für die App, beginnend mit https://.'],
            self::ACCENT_COLOR => ['type' => 'string', 'value' => '', 'description' =>
                'Akzentfarbe der Hochschule als #RRGGBB. Die App leitet daraus ein kontrastsicheres Farbschema ab. Leer: Standardschema.'],
            self::LINK_HOMEPAGE => ['type' => 'string', 'value' => '', 'description' =>
                'Link zur Website der Hochschule für die App, beginnend mit https://.'],
            self::LINK_CAMPUS_MANAGEMENT => ['type' => 'string', 'value' => '', 'description' =>
                'Link zum Campus-Management-System der Hochschule, beginnend mit https://.'],
            self::LINK_LIBRARY => ['type' => 'string', 'value' => '', 'description' =>
                'Link zur Bibliothek der Hochschule, beginnend mit https://.'],
            self::LINK_IT_HELP => ['type' => 'string', 'value' => '', 'description' =>
                'Link zur IT-Hilfe der Hochschule, beginnend mit https://.'],
            self::MENSA_OPENMENSA_IDS => ['type' => 'string', 'value' => '', 'description' =>
                'OpenMensa-IDs der Mensen, durch Komma getrennt; die erste ist die voreingestellte. Hat Vorrang vor APP_CONNECT_MENSA_URL.'],
            self::MENSA_URL => ['type' => 'string', 'value' => '', 'description' =>
                'Link zum Web-Speiseplan, wenn die Mensen nicht bei OpenMensa stehen, beginnend mit https://.'],
            self::ROOM_FINDER_PROPERTY => ['type' => 'string', 'value' => '', 'description' =>
                'Name der Raum-Eigenschaft der Raumverwaltung, die einen Link zum Raumfinder der Hochschule enthält (z. B. „Lageplan“). Die App zeigt nur https-Links. Leer: kein Link.'],
        ];
    }

    public static function isEmail(mixed $value): bool
    {
        return is_string($value) && strlen(trim($value)) <= 254
            && filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function isHexColor(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($value)) === 1;
    }

    /**
     * OpenMensa canteen ids from "12, 34": non-negative integers in order,
     * without duplicates; null when the value holds anything else.
     *
     * @return list<int>|null
     */
    public static function canteenIds(mixed $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $ids = [];
        foreach (explode(',', $value) as $part) {
            if (preg_match('/^\s*(\d{1,9})\s*$/', $part, $m) !== 1) {
                return null;
            }
            $ids[] = (int) $m[1];
        }
        return array_values(array_unique($ids));
    }

    public static function isHttpsUrl(mixed $value): bool
    {
        return is_string($value) && preg_match('~^https://[^\s/?#@]+(/\S*)?$~', trim($value)) === 1;
    }
}
