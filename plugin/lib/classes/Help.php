<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/** Short help on the plugin's pages, in the core's help bar. */
final class Help
{
    public const CONNECT = 'connect';
    public const GET = 'get';

    /** @return list<string> */
    public static function texts(string $page, bool $pairingEnabled = true): array
    {
        if ($page === self::GET) {
            return [
                Translation::text('Über diese Seite kommen Sie zur Stud.App, der App für Stud.IP auf iPhone und Android-Geräten.'),
                Translation::text('Ist die App installiert, öffnet „In der App öffnen“ sie direkt. Kommen Sie über den QR-Code aus Ihrem Profil, verbindet sich die App dabei mit Ihrem Konto.'),
            ];
        }
        $texts = $pairingEnabled
            ? [
                Translation::text('Auf dieser Seite verbinden Sie die Stud.App mit Ihrem Konto. Erzeugen Sie einen QR-Code und scannen Sie ihn mit der App; eine Anmeldung in der App ist danach nicht mehr nötig.'),
                Translation::text('Der QR-Code gilt fünf Minuten und nur einmal. Wer ihn scannt, erhält Zugang zu Ihrem Konto. Zeigen Sie ihn deshalb niemandem.'),
            ]
            : [
                Translation::text('Auf dieser Seite sehen Sie, welche Geräte mit der Stud.App auf Ihr Konto zugreifen. Verbunden wird die App über die Anmeldung in der App.'),
            ];
        $texts[] = Translation::text('Unter „Mit der App verbundene Geräte“ steht jedes Gerät, das Zugang zu Ihrem Konto hat. Entfernen Sie ein Gerät, verliert es den Zugang sofort, etwa wenn es verloren gegangen ist.');
        return $texts;
    }

    public static function addToHelpbar(string $page, bool $pairingEnabled = true): void
    {
        foreach (self::texts($page, $pairingEnabled) as $text) {
            \Helpbar::get()->addPlainText('', $text);
        }
    }

    private function __construct()
    {
    }
}
