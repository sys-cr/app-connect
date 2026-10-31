<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Profile;

/**
 * The language a person uses Stud.IP in: the one chosen in the profile when
 * the site still offers it, else the site's default language.
 */
final class UserLanguage
{
    /**
     * @param list<string> $installed Locales the site offers (`de_DE`, `en_GB`, …).
     * @return array{language: string, source: 'profile'|'default'}
     */
    public static function resolve(?string $preferred, array $installed, string $default): array
    {
        $preferred = trim((string) $preferred);
        if ($preferred !== '' && in_array($preferred, $installed, true)) {
            return ['language' => $preferred, 'source' => 'profile'];
        }
        return ['language' => $default, 'source' => 'default'];
    }
}
