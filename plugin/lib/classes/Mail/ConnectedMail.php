<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Mail;

use AppConnect\Devices\DeviceRecord;
use AppConnect\Runtime;
use AppConnect\Translation;
use Throwable;

/**
 * "Device connected" mail, sent after every successful connection – pairing
 * and browser sign-in alike – to the account's address in its language. No IP
 * address, no code, no one-click removal: the link leads to the profile page.
 */
final class ConnectedMail
{
    /** Sends the mail; a failure is logged and never undoes the connection. */
    public static function send(string $userId, DeviceRecord $device): void
    {
        try {
            $user = \User::find($userId);
            if ($user === null || trim((string) $user->email) === '') {
                return;
            }
            setTempLanguage($userId);
            try {
                $texts = self::texts(
                    (string) $device->name,
                    (string) $device->platform,
                    (string) $device->paired_via,
                    (int) $device->paired_at,
                    Runtime::canonicalBaseUrl() . '/plugins.php/appconnect/connect'
                );
            } finally {
                restoreLanguage();
            }
            \StudipMail::sendMessage((string) $user->email, $texts['subject'], $texts['body']);
        } catch (Throwable $exception) {
            error_log('app-connect: connected mail not sent (' . get_class($exception) . ')');
        }
    }

    /**
     * Subject and body in the current language; separate so the wording can
     * be tested.
     *
     * @return array{subject: string, body: string}
     */
    public static function texts(string $deviceName, string $platform, string $via, int $time, string $profileUrl): array
    {
        $platformLabel = $platform === 'ios' ? 'iOS' : 'Android';
        $viaLabel = $via === 'pairing' ? Translation::text('QR-Code') : Translation::text('Browser-Anmeldung');
        return [
            'subject' => Translation::text('Gerät verbunden'),
            'body' => sprintf(
                Translation::text(
                    "Mit Ihrem Konto wurde ein Gerät verbunden:\n\n"
                    . "Gerät: %1\$s (%2\$s)\n"
                    . "Zeitpunkt: %3\$s\n"
                    . "Verbunden per: %4\$s\n\n"
                    . "Waren Sie das nicht? Dann entfernen Sie das Gerät auf dieser Seite und ändern Sie Ihr Passwort:\n"
                    . "%5\$s\n"
                ),
                $deviceName,
                $platformLabel,
                date(Translation::text('d.m.Y, H:i T'), $time),
                $viaLabel,
                $profileUrl
            ),
        ];
    }
}
