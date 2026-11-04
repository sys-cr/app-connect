<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * Author, source repository and license of the plugin. The additional terms
 * in SUPPLEMENTAL-TERMS.txt require the user interface to show all three;
 * the pages people use, "Stud.App Verbindung" and "App holen", carry them
 * at the end of the help bar, below the help texts. Forks may move the
 * notice but must keep it complete.
 */
final class Attribution
{
    public const AUTHOR = 'Bodo Steffen';

    public const SOURCE_URL = 'https://github.com/sys-cr/app-connect';

    /** SPDX identifier of the license. */
    public const LICENSE = 'GPL-3.0-or-later';

    public const LICENSE_URL = 'https://www.gnu.org/licenses/gpl-3.0.html';

    /** Additional terms under section 7 of the GPL-3.0, shipped with the plugin. */
    public const TERMS_FILE = 'SUPPLEMENTAL-TERMS.txt';

    private static bool $queued = false;

    /** The notice: author, license with the additional terms, open source. */
    public static function text(): string
    {
        return sprintf(
            Translation::text('app-connect wurde von %s entwickelt und steht unter der GNU GPL 3.0 oder neuer mit Zusatzbedingungen nach Abschnitt 7. Der Quellcode ist öffentlich zugänglich.'),
            self::AUTHOR
        );
    }

    /**
     * The notice with links to the license and the source as one block,
     * smaller and dimmer than the help above it. `$linkIcon` is rendered
     * icon markup and goes in as it is.
     */
    public static function html(string $linkIcon = ''): string
    {
        $icon = $linkIcon !== '' ? $linkIcon . ' ' : '';
        return sprintf(
            '<div class="app-connect-attribution" style="font-size: 0.78em; line-height: 1.45; opacity: 0.65;">'
            . '%s<br>'
            . '<a href="%s" target="_blank" rel="noopener noreferrer" style="color: inherit;">%s%s</a>'
            . ' · '
            . '<a href="%s" target="_blank" rel="noopener noreferrer" style="color: inherit;">%s%s</a>'
            . '</div>',
            htmlReady(self::text()),
            htmlReady(self::LICENSE_URL),
            $icon,
            htmlReady(Translation::text('Lizenz (GPLv3)')),
            htmlReady(self::SOURCE_URL),
            $icon,
            htmlReady(Translation::text('Quellcode (GitHub)'))
        );
    }

    /**
     * Adds the notice as the last entry of the help bar. The core appends
     * "Weiterführende Hilfe" while rendering, so the notice follows on the
     * notification sent right before.
     */
    public static function addToHelpbar(): void
    {
        if (!self::$queued) {
            self::$queued = true;
            \NotificationCenter::addObserver(self::class, 'onHelpbarWillRender', 'HelpbarWillRender');
        }
    }

    public static function onHelpbarWillRender(string $event, \Helpbar $helpbar): void
    {
        $icon = \Icon::create('link-extern', \Icon::ROLE_INFO_ALT)
            ->asImg(16, ['style' => 'vertical-align: text-bottom;', 'alt' => '']);
        $widget = new \HelpbarWidget();
        $widget->addElement(new \WidgetElement(self::html((string) $icon)));
        $helpbar->addWidget($widget);
    }

    private function __construct()
    {
    }
}
