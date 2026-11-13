<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Integration;

use AppConnect\Connect\PairingCode;
use AppConnect\Pairing\PairingService;
use AppConnect\WebLogin\DatabaseTicketStore;

/** Pairing codes and web-login tickets work once, also under concurrent requests. */
final class SingleUseTest extends IntegrationTestCase
{
    private const USER = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    public function testAPairingCodeRedeemedTenTimesAtOnceConnectsOnce(): void
    {
        $code = '7K2M9QX4T8WZ3RBN6FJ5HCDV0A';
        \DBManager::get()->execute(
            'INSERT INTO `app_connect_pairing_codes` (`code_hash`, `user_id`, `expires_at`, `mkdate`) VALUES (?, ?, ?, ?)',
            [PairingCode::hash($code), self::USER, time() + 300, time()]
        );

        $results = $this->concurrently(10, static fn (): ?string => (new PairingService())->redeem(
            $code,
            sprintf('00000000-0000-4000-8000-%012d', getmypid())
        ));

        self::assertSame([self::USER], array_values(array_filter($results)));
        self::assertNull((new PairingService())->redeem($code, '00000000-0000-4000-8000-000000000000'));
    }

    public function testAnExpiredPairingCodeIsRefused(): void
    {
        $code = '7K2M9QX4T8WZ3RBN6FJ5HCDV0B';
        \DBManager::get()->execute(
            'INSERT INTO `app_connect_pairing_codes` (`code_hash`, `user_id`, `expires_at`, `mkdate`) VALUES (?, ?, ?, ?)',
            [PairingCode::hash($code), self::USER, time() - 1, time() - 301]
        );

        self::assertNull((new PairingService())->redeem($code, '00000000-0000-4000-8000-000000000000'));
    }

    public function testAWebLoginTicketClaimedTenTimesAtOnceWorksOnce(): void
    {
        $store = new DatabaseTicketStore();
        $hash = hash('sha256', 'ticket');
        $store->add($hash, [
            'user_id' => self::USER, 'return_to' => null, 'access_token_id' => 'a1', 'device_row_id' => null,
            'created_at' => time(), 'expires_at' => time() + 60,
        ]);

        $results = $this->concurrently(10, static fn (): ?string
            => (new DatabaseTicketStore())->claim($hash, time())['user_id'] ?? null);

        self::assertSame([self::USER], array_values(array_filter($results)));
    }
}
