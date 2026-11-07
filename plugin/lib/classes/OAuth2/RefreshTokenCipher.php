<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

use League\OAuth2\Server\CryptTrait;

/**
 * Opens refresh tokens the core issued, with the core's encryption key and
 * the same routine its refresh grant uses.
 */
final class RefreshTokenCipher
{
    use CryptTrait;

    public function __construct(mixed $encryptionKey)
    {
        $this->setEncryptionKey($encryptionKey);
    }

    /** The plaintext of $token; null when it does not decrypt with this key. */
    public function open(string $token): ?string
    {
        try {
            return $this->decrypt($token);
        } catch (\LogicException $exception) {
            return null;
        }
    }

    /**
     * A token inspector for the app client, with the keys of the core's
     * OAuth2 setup (config/oauth2). Null when the keys cannot be read.
     */
    public static function inspector(string $clientId): ?TokenInspector
    {
        $container = new \Studip\OAuth2\Container();
        $keyFile = (string) $container->get('encryption_key');
        $publicKeyFile = preg_replace('~^file://~', '', (string) $container->get('public_key'));
        if (!is_readable($keyFile) || !is_readable($publicKeyFile)) {
            return null;
        }
        $publicKey = (string) file_get_contents($publicKeyFile);
        $cipher = new self(include $keyFile);
        return new TokenInspector($clientId, $publicKey, $cipher->open(...));
    }
}
