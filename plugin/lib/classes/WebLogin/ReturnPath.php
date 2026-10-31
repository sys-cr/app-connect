<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\WebLogin;

/**
 * The page a web-login ticket leads to: a path relative to the Stud.IP base
 * URL. The redirect is always built from the configured base URL, so a path
 * that passes here can never point at another host or leave the instance.
 */
final class ReturnPath
{
    public const MAX_LENGTH = 2048;

    /** Paths may start with one of these; index.php and seminar_main.php stand alone. */
    public const PREFIXES = ['dispatch.php/', 'plugins.php/', 'index.php', 'seminar_main.php'];

    /** Rounds of percent-decoding inspected; deeper nesting is refused. */
    private const DECODE_ROUNDS = 4;

    /**
     * The path unchanged when it is acceptable, null for anything else:
     * schemes, hosts, "//", backslashes, control characters, dot segments
     * (also percent-encoded, also double-encoded), characters outside
     * RFC 3986 and paths outside the allowed prefixes. One fragment is allowed.
     */
    public static function parse(mixed $value): ?string
    {
        if (!is_string($value) || $value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }
        // RFC 3986 characters only; this rules out spaces, quotes, angle
        // brackets, backslashes, control characters and raw non-ASCII bytes.
        if (preg_match('~^[A-Za-z0-9\-._\~:/?#\[\]@!$&\'()*+,;=%]+$~', $value) !== 1
            || preg_match('/%(?![0-9A-Fa-f]{2})/', $value) === 1
            || substr_count($value, '#') > 1
            || !self::hasAllowedPrefix($value)) {
            return null;
        }
        $form = $value;
        for ($round = 0; ; $round++) {
            if (!self::isHarmless($form)) {
                return null;
            }
            $decoded = rawurldecode($form);
            if ($decoded === $form) {
                return $value;
            }
            if ($round === self::DECODE_ROUNDS) {
                return null;
            }
            $form = $decoded;
        }
    }

    /** The absolute address of an accepted path below $baseUrl. */
    public static function absoluteUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . $path;
    }

    private static function hasAllowedPrefix(string $value): bool
    {
        foreach (self::PREFIXES as $prefix) {
            if (!str_starts_with($value, $prefix)) {
                continue;
            }
            if (str_ends_with($prefix, '/')) {
                return true;
            }
            $next = substr($value, strlen($prefix), 1);
            return $next === '' || $next === '?' || $next === '#';
        }
        return false;
    }

    /** Checks one (possibly decoded) form of the path. */
    private static function isHarmless(string $form): bool
    {
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $form) === 1
            || str_contains($form, '//')
            || preg_match('~^[A-Za-z][A-Za-z0-9+.\-]*:~', $form) === 1) {
            return false;
        }
        foreach (preg_split('~[/?#&=;]~', $form) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }
}
