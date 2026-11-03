<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * Adds the switch for Courseware in the app to the section "Stud.App".
 */
return new class extends Migration
{
    public const FIELDS = [
        'APP_CONNECT_FEATURE_COURSEWARE' => [
            'type' => 'boolean',
            'value' => 1,
            'description' => 'Zeigt in der App die Courseware der Veranstaltungen zum Lesen. Wirkt nur, wenn das Modul Courseware aktiv ist.',
        ],
    ];

    public function description()
    {
        return 'Adds the app-connect switch for Courseware.';
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
