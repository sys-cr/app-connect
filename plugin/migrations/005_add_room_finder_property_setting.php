<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * Adds the configuration entry naming the room finder property.
 */
return new class extends Migration
{
    public const FIELDS = [
        'APP_CONNECT_ROOM_FINDER_PROPERTY' => [
            'type' => 'string',
            'value' => '',
            'description' => 'Name der Raum-Eigenschaft der Raumverwaltung, die einen Link zum Raumfinder der Hochschule enthält (z. B. „Lageplan“). Die App zeigt nur https-Links. Leer: kein Link.',
        ],
    ];

    public function description()
    {
        return 'Adds the app-connect configuration entry for the room finder link.';
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
