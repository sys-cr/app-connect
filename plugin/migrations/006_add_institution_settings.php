<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Settings\ConfigBackedSettingsStore;
use AppConnect\Settings\DatabaseSettingsStore;
use AppConnect\Settings\OperatorConfig;
use AppConnect\Settings\Settings;
use AppConnect\Settings\StudipConfigAccess;

/**
 * Adds the institution settings of the section "Stud.App" – support address,
 * logo, accent colour, links, mensa source and the switches for messages and
 * places – and carries branding and links over from the plugin table.
 */
return new class extends Migration
{
    public function description()
    {
        return 'Adds the app-connect institution settings (support, branding, links, mensa, feature switches).';
    }

    protected function up()
    {
        $config = Config::get();
        foreach (OperatorConfig::definitions() as $field => $definition) {
            if (!$config->getMetadata($field)) {
                $config->create($field, [
                    'value' => $definition['type'] === 'boolean' ? (int) $definition['value'] : $definition['value'],
                    'type' => $definition['type'],
                    'range' => 'global',
                    'section' => OperatorConfig::SECTION,
                    'description' => $definition['description'],
                ]);
            }
        }

        $table = new DatabaseSettingsStore();
        $store = new ConfigBackedSettingsStore(new StudipConfigAccess(), $table);
        foreach ([Settings::BRANDING, Settings::LINKS] as $key) {
            $value = $table->get($key);
            if ($value !== null) {
                $store->set($key, $value);
                $table->set($key, null);
            }
        }
    }

    protected function down()
    {
        $table = new DatabaseSettingsStore();
        $store = new ConfigBackedSettingsStore(new StudipConfigAccess(), $table);
        foreach ([Settings::BRANDING, Settings::LINKS] as $key) {
            $table->set($key, $store->get($key));
        }
        $fields = [
            OperatorConfig::FEATURE_MESSAGES, OperatorConfig::FEATURE_EVENT_PLACES, OperatorConfig::SUPPORT_EMAIL,
            OperatorConfig::LOGO_URL, OperatorConfig::ACCENT_COLOR, OperatorConfig::MENSA_OPENMENSA_IDS,
            OperatorConfig::MENSA_URL, ...array_values(OperatorConfig::LINK_FIELDS),
        ];
        foreach ($fields as $field) {
            Config::get()->delete($field);
        }
    }
};
