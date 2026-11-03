<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * The switch of the calendar route in the section "Stud.App", on by
 * default like the other routes the plugin serves.
 */
return new class extends Migration
{
    public const FIELDS = [
        'APP_CONNECT_FEATURE_USER_EVENTS' => [
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Liefert der App den Kalender der Person in einem Abruf, mit den Farben der Veranstaltungen wie im Web-Kalender. Ausgeschaltet setzt die App den Kalender aus den Routen des Kerns zusammen.',
        ],
    ];

    public function description()
    {
        return 'Adds the switch of the app-connect calendar route.';
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
