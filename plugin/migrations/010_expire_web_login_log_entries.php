<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Audit\EventLog;

/**
 * Web-login entries in the event log name the person. The core keeps entries
 * of actions without an expiry for good, so this sets one.
 */
return new class extends Migration
{
    public function description()
    {
        return 'Lets the event log delete web-login entries of app-connect after 90 days.';
    }

    protected function up()
    {
        $this->setExpiry(EventLog::WEB_LOGIN_RETENTION_SECONDS);
    }

    protected function down()
    {
        $this->setExpiry(0);
    }

    private function setExpiry(int $seconds): void
    {
        foreach (EventLog::WEB_LOGIN_ACTIONS as $name) {
            $action = LogAction::findOneByName($name);
            if ($action) {
                $action->expires = $seconds;
                $action->store();
            }
        }
    }
};
