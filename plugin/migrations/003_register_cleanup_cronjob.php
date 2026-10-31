<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

return new class extends Migration
{
    public function description()
    {
        return 'Registers the daily cleanup cronjob of app-connect.';
    }

    protected function up()
    {
        require_once __DIR__ . '/../cronjobs/AppConnectCleanupJob.php';
        // Daily at 03:17, away from the full hour most jobs use.
        AppConnectCleanupJob::register()->schedulePeriodic(17, 3)->activate();
    }

    protected function down()
    {
        require_once __DIR__ . '/../cronjobs/AppConnectCleanupJob.php';
        AppConnectCleanupJob::unregister();
    }
};
