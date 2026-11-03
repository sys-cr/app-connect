<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * Brings the descriptions of the entries created by migrations 004 to 007 up
 * to date; Config::create() writes a description only once.
 */
return new class extends Migration
{
    private const MIGRATIONS = [
        '004_move_operator_settings_to_config',
        '005_add_room_finder_property_setting',
        '006_add_institution_settings',
        '007_add_courseware_switch',
    ];

    public function description()
    {
        return 'Refreshes the descriptions of the app-connect configuration entries.';
    }

    protected function up()
    {
        $statement = DBManager::get()->prepare('UPDATE `config` SET `description` = ? WHERE `field` = ?');
        foreach (self::MIGRATIONS as $name) {
            $migration = require __DIR__ . '/' . $name . '.php';
            foreach ($migration::FIELDS as $field => $definition) {
                $statement->execute([$definition['description'], $field]);
            }
        }
    }

    protected function down()
    {
    }
};
