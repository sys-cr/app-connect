<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * The plugin's legal notice: author and copyright, the license with its
 * additional terms and a link to the license text. The additional terms in
 * SUPPLEMENTAL-TERMS.txt require the notices to keep the author attribution
 * and the license notice; wording, place and design are free. The pages people use, "Stud.App Verbindung" and
 * "App holen", carry them at the end of the help bar, below the help texts.
 */
final class Attribution
{
    public const AUTHOR = 'Bodo Steffen';

    public const COPYRIGHT_YEAR = '2026';

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

    /** The notice: author and copyright, license with the additional terms. */
    public static function text(): string
    {
        return sprintf(
            Translation::text('app-connect wurde von %1$s (© %2$s) entwickelt. Das Plugin ist freie Software unter der GNU GPL 3.0 oder neuer mit Zusatzbedingungen nach Abschnitt 7.'),
            self::AUTHOR,
            self::COPYRIGHT_YEAR
        );
    }

    /**
     * The notice with a link to the license as one block,
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
            . '</div>',
            $inHelpbar ? self::HELPBAR_COLOR : self::PAGE_COLOR,
            htmlReady(self::text()),
            htmlReady(self::LICENSE_URL),
            $icon,
            htmlReady(Translation::text('Lizenz (GPLv3)'))
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
