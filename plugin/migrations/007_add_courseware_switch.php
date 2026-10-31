<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Settings\OperatorConfig;

/**
 * Adds the switch for Courseware in the app to the section "Stud.App".
 */
return new class extends Migration
{
    public function description()
    {
        return 'Adds the app-connect switch for Courseware.';
    }

    protected function up()
    {
        $definition = OperatorConfig::definitions()[OperatorConfig::FEATURE_COURSEWARE];
        if (!Config::get()->getMetadata(OperatorConfig::FEATURE_COURSEWARE)) {
            Config::get()->create(OperatorConfig::FEATURE_COURSEWARE, [
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
        Config::get()->delete(OperatorConfig::FEATURE_COURSEWARE);
    }
};
