<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Devices\DeviceRetention;
use AppConnect\Pairing\PairingService;
use AppConnect\WebLogin\DatabaseTicketStore;
use AppConnect\WebLogin\WebLoginService;

/**
 * Daily cleanup of app-connect: pairing codes a day after they expired,
 * web-login tickets a day after they were issued, and device rows a month
 * after their refresh token expired (removed devices and devices that were
 * never used again). Registered by migration 003; the cron
 * runner loads this file on its own, hence the explicit bootstrap.
 */
class AppConnectCleanupJob extends CronJob
{
    public static function getName()
    {
        return 'app-connect: Aufräumen';
    }

    public static function getDescription()
    {
        return 'Löscht abgelaufene Kopplungscodes, Web-Anmeldelinks und Gerätezeilen der App-Anbindung nach Ablauf ihrer Aufbewahrungsfrist.';
    }

    public function execute($last_result, $parameters = [])
    {
        (new PairingService())->purge();
        (new WebLoginService(new DatabaseTicketStore()))->purge();
        $removed = (new DeviceRetention())->purge(time());
        return sprintf('%d device rows removed', $removed);
    }
}
