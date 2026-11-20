<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

use AppConnect\Format;
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
     * @return array{0: 'success'|'info'|'warning'|'error', 1: string}|null kind and plain text
     */
    public static function outcome(?StoredLogo $logo): ?array
    {
        if ($logo === null) {
            return null;
        }
        if ($logo->isUpload()) {
            return ['info', Translation::text('Es gilt das hochgeladene Logo. Die Adresse in APP_CONNECT_LOGO_URL verwenden die Apps erst, wenn Sie das hochgeladene Logo auf der Seite „Stud.App Verbindung“ entfernen.')];
        }
        if ($logo->problem === null && $logo->image !== null) {
            return ['success', Translation::text('Das Logo wurde geladen. Die Apps erhalten es von diesem Server.')];
        }
        $reason = self::reason((string) $logo->problem);
        if ($logo->image !== null) {
            return ['warning', sprintf(
                Translation::text('Das Logo konnte nicht neu geladen werden: %s Die Apps erhalten weiter die Fassung vom %s.'),
                $reason,
                Format::dateTime((int) $logo->fetchedAt)
            )];
        }
        return ['error', sprintf(Translation::text('Das Logo (APP_CONNECT_LOGO_URL) wird nicht verwendet: %s'), $reason)];
    }

    /**
     * The messages of the logo box on "Stud.App Verbindung": what the apps
     * get, and what went wrong at the last fetch.
     *
     * @return list<array{0: 'success'|'info'|'warning'|'error', 1: string}> kind and plain text
     */
    public static function describe(?StoredLogo $logo): array
    {
        if ($logo === null) {
            return [['info', Translation::text('Das Logo wurde noch nicht abgerufen.')]];
        }
        if ($logo->image === null) {
            return [['error', sprintf(
                Translation::text('Die Apps erhalten kein Logo. Der Abruf am %s ist fehlgeschlagen: %s'),
                Format::dateTime($logo->checkedAt),
                self::reason((string) $logo->problem)
            )]];
        }
        $messages = [['success', sprintf(
            $logo->isUpload()
                ? Translation::text('Hochgeladen am %s (%s, %u × %u Pixel). Die Apps erhalten es von diesem Server.')
                : Translation::text('Abgerufen am %s (%s, %u × %u Pixel). Die Apps erhalten es von diesem Server.'),
            Format::dateTime((int) $logo->fetchedAt),
            $logo->image->typeLabel(),
            $logo->image->width,
            $logo->image->height
        )]];
        if ($logo->problem !== null && !$logo->isUpload()) {
            $messages[] = ['warning', sprintf(
                Translation::text('Der letzte Abruf am %s ist fehlgeschlagen: %s Die Apps erhalten weiter die Fassung oben.'),
                Format::dateTime($logo->checkedAt),
                self::reason($logo->problem)
            )];
        }
        return $messages;
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
            'info' => \PageLayout::postInfo(htmlReady($text)),
            'warning' => \PageLayout::postWarning(htmlReady($text)),
            default => \PageLayout::postError(htmlReady($text)),
        };
    }
}
