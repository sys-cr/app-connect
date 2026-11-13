<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Integration;

use AppConnect\RateLimit\DatabaseCounterStore;

/** The limit counters lose no count under concurrent requests. */
final class CounterTest extends IntegrationTestCase
{
    public function testConcurrentIncrementsAreAllCounted(): void
    {
        $this->concurrently(10, static function (): ?string {
            $store = new DatabaseCounterStore();
            for ($i = 0; $i < 50; $i++) {
                $store->increment('app-connect/test/k', 600);
            }
            return null;
        });

        self::assertSame(500, (new DatabaseCounterStore())->read('app-connect/test/k'));
        self::assertSame(501, (new DatabaseCounterStore())->increment('app-connect/test/k', 600));
    }

    public function testAnExpiredCounterStartsOver(): void
    {
        $store = new DatabaseCounterStore();
        $store->increment('app-connect/test/e', 600);
        \DBManager::get()->execute('UPDATE `app_connect_counters` SET `expires_at` = 1');

        self::assertSame(0, $store->read('app-connect/test/e'));
        self::assertSame(1, $store->increment('app-connect/test/e', 600));
        \DBManager::get()->execute('UPDATE `app_connect_counters` SET `expires_at` = 1');
        self::assertSame(1, DatabaseCounterStore::purge(time()));
    }
}
