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

    /**
     * Text colour of the notice in the help bar: the help bar's text colour
     * shaded towards its background. The background is `--color--brand-primary`
     * from Stud.IP 6.1 on and `--color--info` in 6.0; at 85 % the notice keeps
     * at least 4.5:1 on both and in the core's high-contrast mode.
     */
    public const HELPBAR_COLOR = 'color-mix(in srgb, var(--color--font-inverted) 85%, var(--color--brand-primary))';

    /** Text colour of the notice in the page content. */
    public const PAGE_COLOR = 'var(--color--font-secondary)';

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
     * dimmer than the text around it and set at 14 px, the smallest size
     * the notice may take. `$linkIcon` is rendered
     * icon markup and goes in as it is; `$inHelpbar` picks the text colour
     * for the help bar or for the page content.
     */
    public static function html(string $linkIcon = '', bool $inHelpbar = true): string
    {
        $icon = $linkIcon !== '' ? $linkIcon . ' ' : '';
        return sprintf(
            '<div class="app-connect-attribution" style="font-size: 14px; line-height: 1.75; color: %s;">'
            . '%s<br>'
            . '<a href="%s" target="_blank" rel="noopener noreferrer" style="color: inherit; white-space: nowrap;">%s%s</a>'
            . ' · '
            . '<a href="%s" target="_blank" rel="noopener noreferrer" style="color: inherit; white-space: nowrap;">%s%s</a>'
            . '</div>',
            $inHelpbar ? self::HELPBAR_COLOR : self::PAGE_COLOR,
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
