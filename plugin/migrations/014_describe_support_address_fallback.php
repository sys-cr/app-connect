<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * The description of APP_CONNECT_SUPPORT_EMAIL names where content reports
 * go without an address: the apps send them to the support of the Stud.App
 * project. Config::create() writes a description only once, so it is set
 * here.
 */
return new class extends Migration
{
    /** Field => description that replaces the one an earlier migration created. */
    public const DESCRIPTIONS = [
        'APP_CONNECT_SUPPORT_EMAIL' => 'E-Mail-Adresse des Supports der Hochschule für Hilfe und Meldungen von Inhalten in der App. Bitte eintragen: Ohne Adresse gehen Meldungen an support@studapp2.de (Projekt Stud.App).',
    ];

    public function description()
    {
        return 'Says in the description of the support address where reports go without one.';
    }

    protected function up()
    {
        $statement = DBManager::get()->prepare('UPDATE `config` SET `description` = ? WHERE `field` = ?');
        foreach (self::DESCRIPTIONS as $field => $description) {
            $statement->execute([$description, $field]);
        }
    }

    protected function down()
    {
    }
};
