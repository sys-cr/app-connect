<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * The switch that lets admin and root accounts use the web login from the
 * app. Off by default: those accounts sign in on the web as usual.
 */
return new class extends Migration
{
    public const FIELDS = [
        'APP_CONNECT_WEB_LOGIN_PRIVILEGED' => [
            'type' => 'boolean',
            'value' => 0,
            'description' => 'Erlaubt die Anmeldung im Web aus der App auch für Konten mit den Rollen admin und root. Aus (Vorgabe): Diese Konten melden sich im Browser wie gewohnt an.',
        ],
    ];

    public function description()
    {
        return 'Adds the switch for the web login of admin and root accounts.';
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
