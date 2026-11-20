<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Settings\CachedSettingsStore;
use AppConnect\Settings\SettingsStore;
use PHPUnit\Framework\TestCase;

/** The plugin table is read once per key and request (#27). */
final class CachedSettingsStoreTest extends TestCase
{
    public function testEachKeyIsReadOnceIncludingMissingOnes(): void
    {
        $inner = self::countingStore(['a' => 1]);
        $store = new CachedSettingsStore($inner);

        self::assertSame(1, $store->get('a'));
        self::assertSame(1, $store->get('a'));
        self::assertNull($store->get('b'));
        self::assertNull($store->get('b'));

        self::assertSame(['a' => 1, 'b' => 1], $inner->reads);
    }

    public function testWritesGoThroughAndUpdateTheCopy(): void
    {
        $inner = self::countingStore(['a' => 1]);
        $store = new CachedSettingsStore($inner);
        $store->get('a');

        $store->set('a', 2);
        $store->set('b', ['x' => true]);
        $store->set('c', null);

        self::assertSame(2, $store->get('a'));
        self::assertSame(['x' => true], $store->get('b'));
        self::assertNull($store->get('c'));
        self::assertSame(['a' => 2, 'b' => ['x' => true]], $inner->values);
        self::assertSame(['a' => 1], $inner->reads, 'no read after the writes');
    }

    /** @param array<string, mixed> $values */
    private static function countingStore(array $values): SettingsStore
    {
        return new class ($values) implements SettingsStore {
            /** @var array<string, int> */
            public array $reads = [];

            /** @param array<string, mixed> $values */
            public function __construct(public array $values)
            {
            }

            public function get(string $key): mixed
            {
                $this->reads[$key] = ($this->reads[$key] ?? 0) + 1;
                return $this->values[$key] ?? null;
            }

            public function set(string $key, mixed $value): void
            {
                if ($value === null) {
                    unset($this->values[$key]);
                    return;
                }
                $this->values[$key] = $value;
            }
        };
    }
}
