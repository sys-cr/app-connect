<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Settings\OperatorConfig;

/**
 * The switch of the calendar route in the section "Stud.App", on by
 * default like the other routes the plugin serves.
 */
return new class extends Migration
{
    public function description()
    {
        return 'Adds the switch of the app-connect calendar route.';
    }

    protected function up()
    {
        $definition = OperatorConfig::definitions()[OperatorConfig::FEATURE_USER_EVENTS];
        if (!Config::get()->getMetadata(OperatorConfig::FEATURE_USER_EVENTS)) {
            Config::get()->create(OperatorConfig::FEATURE_USER_EVENTS, [
                'value' => (int) $definition['value'],
                'type' => $definition['type'],
                'range' => 'global',
                'section' => OperatorConfig::SECTION,
                'description' => $definition['description'],
            ]);
        }
    }

    protected function down()
    {
        Config::get()->delete(OperatorConfig::FEATURE_USER_EVENTS);
    }
};
