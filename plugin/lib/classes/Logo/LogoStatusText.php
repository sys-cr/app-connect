<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

use AppConnect\Translation;

/** Texts for the operator about the logo, shared by the configuration hook and "Stud.App Verbindung". */
final class LogoStatusText
{
    /** Why the logo was refused, as one sentence. */
    public static function reason(string $reason): string
    {
        return match ($reason) {
            LogoRejected::ADDRESS => Translation::text('Die Adresse muss mit https:// beginnen und darf keine Zugangsdaten enthalten.'),
            LogoRejected::INTERNAL => Translation::text('Die Adresse zeigt in ein internes Netz; abgerufen werden nur öffentliche Adressen und dieser Server.'),
            LogoRejected::REDIRECT => Translation::text('Die Adresse leitet zu oft oder auf eine Adresse ohne https weiter.'),
            LogoRejected::TOO_LARGE => Translation::text('Das Bild ist zu groß; erlaubt sind höchstens 512 KB.'),
            LogoRejected::TYPE => Translation::text('Das Bild ist kein PNG, JPEG oder WebP. SVG wird nicht unterstützt.'),
            LogoRejected::DIMENSIONS => Translation::text('Das Bild muss je Seite zwischen 32 und 2048 Pixel groß sein.'),
            LogoRejected::BROKEN => Translation::text('Das Bild lässt sich nicht lesen.'),
            default => Translation::text('Das Logo ist nicht erreichbar: keine Antwort in 5 Sekunden, ein Fehler beim Zertifikat oder eine Antwort ohne Status 200.'),
        };
    }

    /**
     * What happened, as a short message after saving the address or after
     * "Logo neu laden"; null when no address is set.
     *
     * @return array{0: 'success'|'warning'|'error', 1: string}|null kind and plain text
     */
    public static function outcome(?StoredLogo $logo): ?array
    {
        if ($logo === null) {
            return null;
        }
        if ($logo->problem === null && $logo->image !== null) {
            return ['success', Translation::text('Das Logo wurde geladen. Die Apps erhalten es von diesem Server.')];
        }
        $reason = self::reason((string) $logo->problem);
        if ($logo->image !== null) {
            return ['warning', sprintf(
                Translation::text('Das Logo konnte nicht neu geladen werden: %s Die Apps erhalten weiter die Fassung vom %s.'),
                $reason,
                date('d.m.Y H:i', (int) $logo->fetchedAt)
            )];
        }
        return ['error', sprintf(Translation::text('Das Logo (APP_CONNECT_LOGO_URL) wird nicht verwendet: %s'), $reason)];
    }

    /** Shows {@see self::outcome()} as a message on the next page. */
    public static function post(?StoredLogo $logo): void
    {
        $outcome = self::outcome($logo);
        if ($outcome === null) {
            return;
        }
        [$kind, $text] = $outcome;
        match ($kind) {
            'success' => \PageLayout::postSuccess(htmlReady($text)),
            'warning' => \PageLayout::postWarning(htmlReady($text)),
            default => \PageLayout::postError(htmlReady($text)),
        };
    }
}
