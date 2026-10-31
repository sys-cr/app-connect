<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Settings\OperatorConfig;

/** Adds the configuration entry naming the room finder property. */
return new class extends Migration
{
    public function description()
    {
        return 'Adds the app-connect configuration entry for the room finder link.';
    }

    protected function up()
    {
        $definition = OperatorConfig::definitions()[OperatorConfig::ROOM_FINDER_PROPERTY];
        if (!Config::get()->getMetadata(OperatorConfig::ROOM_FINDER_PROPERTY)) {
            Config::get()->create(OperatorConfig::ROOM_FINDER_PROPERTY, [
                'value' => $definition['value'],
                'type' => $definition['type'],
                'range' => 'global',
                'section' => OperatorConfig::SECTION,
                'description' => $definition['description'],
            ]);
        }
    }

    protected function down()
    {
        Config::get()->delete(OperatorConfig::ROOM_FINDER_PROPERTY);
    }
};
