<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Audit\EventLog;
use AppConnect\Settings\OperatorConfig;

/**
 * Subscribing the personal calendar from the app: the switch in the section
 * "Stud.App" and the two event-log actions about the key. No table of its
 * own – the key belongs to the core and stays in the person's
 * configuration.
 */
return new class extends Migration
{
    public function description()
    {
        return 'Adds the calendar-key route of app-connect and its switch.';
    }

    protected function up()
    {
        foreach (EventLog::ICAL_KEY_ACTIONS as $name) {
            if (!LogAction::findOneByName($name)) {
                [$description, $template] = EventLog::ACTIONS[$name];
                StudipLog::registerActionPlugin($name, $description, $template, 'AppConnect');
            }
            // Names the person, so it expires like the web-login entries.
            $action = LogAction::findOneByName($name);
            if ($action) {
                $action->expires = EventLog::WEB_LOGIN_RETENTION_SECONDS;
                $action->store();
            }
        }

        $definition = OperatorConfig::definitions()[OperatorConfig::FEATURE_ICAL_KEY];
        if (!Config::get()->getMetadata(OperatorConfig::FEATURE_ICAL_KEY)) {
            Config::get()->create(OperatorConfig::FEATURE_ICAL_KEY, [
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
        Config::get()->delete(OperatorConfig::FEATURE_ICAL_KEY);
        foreach (EventLog::ICAL_KEY_ACTIONS as $name) {
            StudipLog::unregisterAction($name);
        }
    }
};
