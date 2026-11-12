<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use AppConnect\Devices\DeviceRetention;
use AppConnect\Pairing\PairingService;
use AppConnect\Runtime;
use AppConnect\Translation;
use AppConnect\WebLogin\DatabaseTicketStore;
use AppConnect\WebLogin\WebLoginService;

/**
 * Daily cleanup of app-connect: pairing codes a day after they expired,
 * web-login tickets a day after they were issued, and device rows a month
 * after their refresh token expired (removed devices and devices that were
 * never used again). It also fetches the configured institution logo again,
 * so the plugin's copy follows a picture that changed at its address and a
 * copy whose address was removed is deleted. Registered by migration 003;
 * the cron runner loads this file on its own, hence the explicit bootstrap.
 */
class AppConnectCleanupJob extends CronJob
{
    public static function getName()
    {
        return Translation::text('app-connect: Aufräumen');
    }

    public static function getDescription()
    {
        return Translation::text(
            'Löscht abgelaufene Kopplungscodes, Web-Anmeldelinks und Gerätezeilen der App-Anbindung '
            . 'nach Ablauf ihrer Aufbewahrungsfrist und lädt das Logo der Hochschule neu.'
        );
    }

    public function execute($last_result, $parameters = [])
    {
        (new PairingService())->purge();
        (new WebLoginService(new DatabaseTicketStore()))->purge();
        $removed = (new DeviceRetention())->purge(time());
        $logo = Runtime::logo()->refresh(Runtime::configuredLogoUrl());
        $logoState = match (true) {
            $logo === null => 'no logo',
            $logo->problem === null => 'logo fetched',
            default => 'logo refresh failed: ' . $logo->problem,
        };
        return sprintf('%d device rows removed, %s', $removed, $logoState);
    }
}
