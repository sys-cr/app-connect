<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Calendar;

/**
 * The subscription URL of a personal Stud.IP calendar.
 *
 * Stud.IP serves the feed at `dispatch.php/ical/index/<key>` without a
 * session: the key is the whole authorisation, which is why a calendar app
 * can poll it at all. The plugin only hands the key out – it is the core's
 * own key (`IcalExport`), stored in the person's configuration, and nothing
 * here duplicates that logic.
 *
 * `webcal://` rather than `https://` so the system calendar subscribes
 * instead of downloading one snapshot. Calendar apps fetch webcal over https
 * on the default port, so a site on another port or without TLS gets no URL
 * at all; a link that quietly pointed somewhere else would be worse than
 * none.
 */
final class IcalSubscription
{
    /** Path of the core's iCal export, below the site's web root. */
    public const EXPORT_PATH = 'dispatch.php/ical/index/';

    /**
     * Whether the key can stand in a URL path as it is. The core's
     * `IcalExport::makeKey()` writes eight letters and digits, and
     * `setKeyLength()` lets a site choose another length, so the range is
     * generous – but the alphabet is not, because anything outside it would
     * have to be escaped and would no longer match the stored key.
     */
    public static function isValidKey(mixed $key): bool
    {
        return is_string($key) && preg_match('/^[A-Za-z0-9]{6,32}$/', $key) === 1;
    }

    /**
     * `webcal://<host>/dispatch.php/ical/index/<key>`, or `null` when the
     * site is not reachable the way a calendar app needs it (no https, a
     * port other than 443) or the key is unusable.
     */
    public static function subscribeUrl(string $baseUrl, string $key): ?string
    {
        if (!self::isValidKey($key)) {
            return null;
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return null;
        }
        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || !in_array((int) ($parts['port'] ?? 443), [443], true)) {
            return null;
        }
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        return 'webcal://' . $host . $path . '/' . self::EXPORT_PATH . $key;
    }

    /**
     * The route's answer for one key.
     *
     * `subscribeUrl` is `null` where the site cannot be subscribed to over
     * webcal; the apps then fall back to showing the key, which still works
     * when the person pastes it into a calendar themselves.
     *
     * @return array{key: string, subscribeUrl: ?string, active: bool}
     */
    public static function payload(string $baseUrl, string $key): array
    {
        return [
            'key' => $key,
            'subscribeUrl' => self::subscribeUrl($baseUrl, $key),
            'active' => true,
        ];
    }
}
