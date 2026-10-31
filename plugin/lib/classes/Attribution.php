<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect;

/**
 * Author, source repository and license of the plugin. The additional terms
 * in SUPPLEMENTAL-TERMS.txt require the user interface to show all three;
 * the pages people use – "Stud.App Verbindung" and "App holen" – carry them
 * in the help bar. Forks may move the notice but must keep it complete.
 */
final class Attribution
{
    public const AUTHOR = 'Bodo Steffen';

    public const SOURCE_URL = 'https://github.com/sys-cr/app-connect';

    /** SPDX identifier of the license. */
    public const LICENSE = 'GPL-3.0-or-later';

    /** Additional terms under section 7 of the GPL-3.0, shipped with the plugin. */
    public const TERMS_FILE = 'SUPPLEMENTAL-TERMS.txt';

    /** The notice, one line each for author, source and license. */
    public static function helpText(): string
    {
        return sprintf(
            _("Autor: %1\$s\nQuellcode: %2\$s\nLizenz: %3\$s mit Zusatzbedingungen nach Abschnitt 7 der GPL-3.0 (%4\$s)"),
            self::AUTHOR,
            self::SOURCE_URL,
            self::LICENSE,
            self::TERMS_FILE
        );
    }

    /** Adds the notice to the help bar of the current page. */
    public static function addToHelpbar(): void
    {
        \Helpbar::get()->addPlainText(_('Über dieses Plugin'), self::helpText());
    }

    private function __construct()
    {
    }
}
