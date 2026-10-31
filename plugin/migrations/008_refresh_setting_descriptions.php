<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Settings\OperatorConfig;

/**
 * Brings the descriptions of the entries in section "Stud.App" up to date;
 * Config::create() writes a description only once.
 */
return new class extends Migration
{
    public function description()
    {
        return 'Refreshes the descriptions of the app-connect configuration entries.';
    }

    protected function up()
    {
        $statement = DBManager::get()->prepare('UPDATE `config` SET `description` = ? WHERE `field` = ?');
        foreach (OperatorConfig::definitions() as $field => $definition) {
            $statement->execute([$definition['description'], $field]);
        }
    }

    protected function down()
    {
    }
};
