<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * Web-login entries in the event log name the person. The core keeps entries
 * of actions without an expiry for good, so this sets one.
 */
return new class extends Migration
{
    public const ACTIONS = [
        'APPCONNECT_WEB_LOGIN_ISSUE',
        'APPCONNECT_WEB_LOGIN_REDEEM',
        'APPCONNECT_WEB_LOGIN_REJECT',
    ];

    /** 90 days (`log_actions.expires`). */
    public const RETENTION_SECONDS = 7776000;

    public function description()
    {
        return 'Lets the event log delete web-login entries of app-connect after 90 days.';
    }

    protected function up()
    {
        $this->setExpiry(self::RETENTION_SECONDS);
    }

    protected function down()
    {
        $this->setExpiry(0);
    }

    private function setExpiry(int $seconds): void
    {
        foreach (self::ACTIONS as $name) {
            $action = LogAction::findOneByName($name);
            if ($action) {
                $action->expires = $seconds;
                $action->store();
            }
        }
    }
};
