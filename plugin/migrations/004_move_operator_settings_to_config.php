<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Settings\ConfigBackedSettingsStore;
use AppConnect\Settings\DatabaseSettingsStore;
use AppConnect\Settings\OperatorConfig;
use AppConnect\Settings\StudipConfigAccess;

/**
 * Moves the operator settings from the plugin table into configuration
 * entries of the section "Stud.App" (Admin → System → Konfiguration) and
 * carries the current values over.
 */
return new class extends Migration
{
    public function description()
    {
        return 'Moves the app-connect operator settings into the configuration section "Stud.App".';
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
        foreach (ConfigBackedSettingsStore::OPERATOR_KEYS as $key) {
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
        foreach (ConfigBackedSettingsStore::OPERATOR_KEYS as $key) {
            $table->set($key, $store->get($key));
        }
        foreach (array_keys(OperatorConfig::definitions()) as $field) {
            Config::get()->delete($field);
        }
    }
};
