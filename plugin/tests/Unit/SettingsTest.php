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

    public function testConnectedNeedsTheSwitchAndTheClient(): void
    {
        self::assertFalse((new Settings(new InMemorySettingsStore([Settings::ENABLED => true])))->isConnected());
        self::assertFalse((new Settings(new InMemorySettingsStore([Settings::OAUTH_CLIENT_ID => '7'])))->isConnected());
        self::assertTrue((new Settings(new InMemorySettingsStore([Settings::ENABLED => true, Settings::OAUTH_CLIENT_ID => '7'])))->isConnected());
    }

    public function testAFeatureIsOnUnlessSwitchedOff(): void
    {
        $settings = new Settings(new InMemorySettingsStore([Settings::FEATURES => ['icalKey' => false, 'userEvents' => 0]]));

        self::assertFalse($settings->isFeatureOn('icalKey'));
        self::assertTrue($settings->isFeatureOn('userEvents'), 'only false switches off');
        self::assertTrue($settings->isFeatureOn('eventPlaces'));
        self::assertTrue((new Settings(new InMemorySettingsStore()))->isFeatureOn('icalKey'));
    }

    public function testTheRateLimitKeyIsCreatedOnceAndKept(): void
    {
        $store = new InMemorySettingsStore();
        $settings = new Settings($store);

        $key = $settings->rateLimitKey();

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
        self::assertSame($key, $settings->rateLimitKey());
        self::assertSame($key, (new Settings($store))->rateLimitKey());
        self::assertNotSame($key, (new Settings(new InMemorySettingsStore()))->rateLimitKey());
    }

    public function testAMalformedRateLimitKeyIsReplaced(): void
    {
        $settings = new Settings(new InMemorySettingsStore([Settings::RATE_LIMIT_KEY => 'short']));

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $settings->rateLimitKey());
    }
}
