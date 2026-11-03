<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * The operator settings as configuration entries of the section "Stud.App"
 * (Admin → System → Konfiguration): the switches of the connection and the
 * instance data. The plugin table holds no operator settings, so nothing is
 * carried over from it.
 */
return new class extends Migration
{
    public const FIELDS = [
        'APP_CONNECT_ENABLED' => [
            'type' => 'boolean',
            'value' => 0,
            'description' => 'Schaltet die Anbindung der Stud.App ein. Beim Einschalten legt das Plugin einen öffentlichen OAuth2-Client an oder verwendet den bisherigen wieder; beim Ausschalten wird er widerrufen, angemeldete Apps verlieren den Zugang.',
        ],
        'APP_CONNECT_PAIRING_ENABLED' => [
            'type' => 'boolean',
            'value' => 0,
            'description' => 'Erlaubt das Verbinden per QR-Code aus dem Profil (Kopplungscode, fünf Minuten gültig, einmalig). Aus: Die Apps verbinden sich nur über die Anmeldung im Browser.',
        ],
        'APP_CONNECT_PROFILE_ENTRY' => [
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Zeigt unter Profil → Einstellungen den Eintrag „Stud.App Verbindung“ (QR-Code, verbundene Geräte).',
        ],
        'APP_CONNECT_INSTANCE_NAME' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Name dieser Stud.IP-Installation in der App. Leer: der Name der Installation.',
        ],
        'APP_CONNECT_INSTANCE_SHORT_NAME' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Kurzname dieser Stud.IP-Installation in der App (z. B. für Widgets).',
        ],
        'APP_CONNECT_PRIVACY_POLICY_URL' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Adresse der Datenschutzerklärung der Hochschule, beginnend mit https://. Andere Werte werden nicht verwendet.',
        ],
        'APP_CONNECT_STORE_APP_STORE' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Link zur Stud.App im App Store für die Seite „App holen“, beginnend mit https://.',
        ],
        'APP_CONNECT_STORE_GOOGLE_PLAY' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Link zur Stud.App bei Google Play für die Seite „App holen“, beginnend mit https://.',
        ],
        'APP_CONNECT_FEATURE_COURSE_CATALOGUE' => [
            'type' => 'boolean',
            'value' => 0,
            'description' => 'Zeigt in der App die Veranstaltungssuche und das Vorlesungsverzeichnis.',
        ],
        'APP_CONNECT_FEATURE_BLUBBER' => [
            'type' => 'boolean',
            'value' => 0,
            'description' => 'Zeigt in der App den Blubber-Chat.',
        ],
        'APP_CONNECT_FEATURE_MENSA' => [
            'type' => 'boolean',
            'value' => 0,
            'description' => 'Zeigt in der App den Mensa-Speiseplan (braucht eine Mensa-Datenquelle).',
        ],
    ];

    public function description()
    {
        return 'Moves the app-connect operator settings into the configuration section "Stud.App".';
    }

    protected function up()
    {
        foreach (self::FIELDS as $field => $definition) {
            if (!Config::get()->getMetadata($field)) {
                Config::get()->create($field, $definition + ['range' => 'global', 'section' => 'Stud.App']);
            }
        }
    }

    protected function down()
    {
        foreach (array_keys(self::FIELDS) as $field) {
            Config::get()->delete($field);
        }
    }
};
