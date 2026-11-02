<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Calendar;

/**
 * A new iCal key for the core's export, made by the plugin.
 *
 * The key is the whole authorisation of the feed, which needs no session
 * and never expires. The core's `IcalExport::makeKey()` builds eight
 * characters from `rand()`, a generator whose state can be recovered from
 * its output and whose keys a case-insensitive column reduces to about
 * 2^41 possibilities. A route that creates a key on request would turn that
 * into a loop anyone with an app token can drive, so keys created here come
 * from `random_int()` instead: 32 lower-case letters and digits, about 2^165
 * possibilities, and still within what the core's controller and
 * {@see IcalSubscription::isValidKey()} accept.
 *
 * The key is stored where the core keeps its own (`ICAL_EXPORT_KEY` in the
 * person's configuration), so the export finds it unchanged.
 */
final class IcalKey
{
    public const LENGTH = 32;

    /** Lower case only: the stored value is compared without regard to case. */
    public const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    /** Attempts before giving up on finding a key no one else holds. */
    public const ATTEMPTS = 3;

    /**
     * A random key of {@see LENGTH} characters from {@see ALPHABET}.
     *
     * @param (\Closure(int, int): int)|null $random replaces `random_int` in tests
     */
    public static function generate(?\Closure $random = null): string
    {
        $random ??= static fn (int $min, int $max): int => random_int($min, $max);
        $last = strlen(self::ALPHABET) - 1;
        $key = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $key .= self::ALPHABET[$random(0, $last)];
        }
        return $key;
    }

    /**
     * A key that $isTaken reports as free, or `null` when every attempt hit
     * a key in use – which only a broken generator can cause.
     *
     * @param \Closure(string): bool $isTaken
     * @param (\Closure(int, int): int)|null $random
     */
    public static function unused(\Closure $isTaken, ?\Closure $random = null): ?string
    {
        for ($i = 0; $i < self::ATTEMPTS; $i++) {
            $key = self::generate($random);
            if (!$isTaken($key)) {
                return $key;
            }
        }
        return null;
    }
}
