<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Mail;

use AppConnect\Devices\DeviceRecord;
use AppConnect\Runtime;
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
            $english = !str_starts_with((string) getUserLanguage($userId), 'de');
            $texts = self::texts(
                $english,
                (string) $device->name,
                (string) $device->platform,
                (string) $device->paired_via,
                (int) $device->paired_at,
                Runtime::canonicalBaseUrl() . '/plugins.php/appconnect/connect'
            );
            \StudipMail::sendMessage((string) $user->email, $texts['subject'], $texts['body']);
        } catch (Throwable $exception) {
            error_log('app-connect: connected mail not sent (' . get_class($exception) . ')');
        }
    }

    /**
     * Subject and body; separate so the wording can be tested.
     *
     * @return array{subject: string, body: string}
     */
    public static function texts(
        bool $english,
        string $deviceName,
        string $platform,
        string $via,
        int $time,
        string $profileUrl
    ): array {
        $platformLabel = $platform === 'ios' ? 'iOS' : 'Android';
        $when = date($english ? 'Y-m-d H:i' : 'd.m.Y H:i', $time);
        if ($english) {
            $viaLabel = $via === 'pairing' ? 'QR code' : 'browser sign-in';
            return [
                'subject' => 'Device connected',
                'body' => "A device was connected to your account:\n\n"
                    . "Device: {$deviceName} ({$platformLabel})\n"
                    . "Time: {$when}\n"
                    . "Connected via: {$viaLabel}\n\n"
                    . "If this was not you, remove the device on this page and change your password:\n"
                    . $profileUrl . "\n",
            ];
        }
        $viaLabel = $via === 'pairing' ? 'QR-Code' : 'Browser-Anmeldung';
        return [
            'subject' => 'Gerät verbunden',
            'body' => "Mit deinem Konto wurde ein Gerät verbunden:\n\n"
                . "Gerät: {$deviceName} ({$platformLabel})\n"
                . "Zeitpunkt: {$when}\n"
                . "Verbunden per: {$viaLabel}\n\n"
                . "Warst du das nicht? Dann entferne das Gerät auf dieser Seite und ändere dein Passwort:\n"
                . $profileUrl . "\n",
        ];
    }
}
