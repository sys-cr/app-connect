<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * Subscribing the personal calendar from the app: the switch in the section
 * "Stud.App" and the two event-log actions about the key. No table of its
 * own – the key belongs to the core and stays in the person's
 * configuration.
 */
return new class extends Migration
{
    /** Action name => [description, info template]. */
    public const ACTIONS = [
        'APPCONNECT_ICAL_KEY_CREATE' => [
            'Kalender-Schlüssel aus der App erzeugt',
            '%user lässt aus der App einen Kalender-Schlüssel erzeugen.',
        ],
        'APPCONNECT_ICAL_KEY_RESET' => [
            'Kalender-Schlüssel aus der App zurückgesetzt',
            '%user setzt aus der App den Kalender-Schlüssel zurück.',
        ],
    ];

    /** 90 days (`log_actions.expires`), like the web-login entries. */
    public const RETENTION_SECONDS = 7776000;

    public const FIELDS = [
        'APP_CONNECT_FEATURE_ICAL_KEY' => [
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Kalender aus der App abonnieren: die App holt den iCal-Schlüssel der Person und übergibt die webcal-Adresse an den Systemkalender. Wer den Schlüssel kennt, kann den Kalender lesen. Ausgeschaltet bleibt der Weg über die Kalender-Seite im Browser.',
        ],
    ];

    public function description()
    {
        return 'Adds the calendar-key route of app-connect and its switch.';
    }

    protected function up()
    {
        foreach (self::ACTIONS as $name => [$description, $template]) {
            if (!LogAction::findOneByName($name)) {
                StudipLog::registerActionPlugin($name, $description, $template, 'AppConnect');
            }
            // Names the person, so it expires like the web-login entries.
            $action = LogAction::findOneByName($name);
            if ($action) {
                $action->expires = self::RETENTION_SECONDS;
                $action->store();
            }
        }
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
        foreach (array_keys(self::ACTIONS) as $name) {
            StudipLog::unregisterAction($name);
        }
    }
};
