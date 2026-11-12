<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * Whether an IP address is on the public internet. The server fetches the
 * logo itself, so an address entered in the configuration must not reach
 * services that are only open to the server: loopback, private networks,
 * link-local (cloud metadata), shared and reserved ranges. The ranges are
 * listed here instead of relying on filter_var, whose ranges differ between
 * PHP versions.
 */
final class AddressGuard
{
    private const BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/127', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23', '2001:db8::/32',
        '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    public static function isPublic(string $address): bool
    {
        $packed = @inet_pton($address);
        if ($packed === false) {
            return false;
        }
        foreach (self::BLOCKED as $range) {
            if (self::inRange($packed, $range)) {
                return false;
            }
        }
        return true;
    }

    private static function inRange(string $packed, string $range): bool
    {
        [$network, $bits] = explode('/', $range);
        $base = inet_pton($network);
        if ($base === false || strlen($base) !== strlen($packed)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($packed, $base, $bytes) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($packed[$bytes]) & $mask) === (ord($base[$bytes]) & $mask);
    }
}
