<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Settings\InMemorySettingsStore;
use AppConnect\Settings\Settings;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    public function testOnlyAllowlistedKeysCanBeWritten(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Settings(new InMemorySettingsStore()))->set('push_salt_enc', 'secret');
    }

    public function testOnlyAllowlistedKeysCanBeRead(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Settings(new InMemorySettingsStore(['anything' => 1])))->get('anything');
    }

    public function testDefaults(): void
    {
        $settings = new Settings(new InMemorySettingsStore());

        self::assertFalse($settings->isEnabled());
        self::assertFalse($settings->isPairingEnabled());
        self::assertTrue($settings->isProfileEntryEnabled());
        self::assertNull($settings->clientId());
    }

    public function testFlagsNeedARealBoolean(): void
    {
        $settings = new Settings(new InMemorySettingsStore([Settings::ENABLED => 'yes', Settings::PROFILE_ENTRY => false]));

        self::assertFalse($settings->isEnabled());
        self::assertFalse($settings->isProfileEntryEnabled());
    }
}
