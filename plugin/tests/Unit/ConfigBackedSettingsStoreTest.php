<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Settings\ConfigBackedSettingsStore;
use AppConnect\Settings\InMemoryConfigAccess;
use AppConnect\Settings\InMemorySettingsStore;
use AppConnect\Settings\OperatorConfig;
use AppConnect\Settings\Settings;
use PHPUnit\Framework\TestCase;

final class ConfigBackedSettingsStoreTest extends TestCase
{
    private InMemoryConfigAccess $config;
    private InMemorySettingsStore $table;
    private Settings $settings;

    protected function setUp(): void
    {
        $this->config = new InMemoryConfigAccess();
        $this->table = new InMemorySettingsStore();
        $this->settings = new Settings(new ConfigBackedSettingsStore($this->config, $this->table));
    }

    public function testSwitchesComeFromTheConfiguration(): void
    {
        self::assertFalse($this->settings->isEnabled());
        self::assertTrue($this->settings->isProfileEntryEnabled());
        $this->config->set(OperatorConfig::ENABLED, true);
        $this->config->set(OperatorConfig::PAIRING_ENABLED, true);
        $this->config->set(OperatorConfig::PROFILE_ENTRY, false);
        self::assertTrue($this->settings->isEnabled());
        self::assertTrue($this->settings->isPairingEnabled());
        self::assertFalse($this->settings->isProfileEntryEnabled());
    }

    public function testInternalValuesStayInThePluginTable(): void
    {
        $this->settings->set(Settings::OAUTH_CLIENT_ID, '7');
        self::assertSame('7', $this->table->get(Settings::OAUTH_CLIENT_ID));
        self::assertSame('7', $this->settings->clientId());
    }

    public function testInstanceNamesAreTrimmedAndEmptyMeansUnset(): void
    {
        self::assertSame([], $this->settings->arrayValue(Settings::INSTANCE));
        $this->config->set(OperatorConfig::INSTANCE_NAME, '  Uni Beispiel ');
        $this->config->set(OperatorConfig::INSTANCE_SHORT_NAME, '');
        self::assertSame(['name' => 'Uni Beispiel'], $this->settings->arrayValue(Settings::INSTANCE));
    }

    public function testAddressesThatAreNotHttpsAreIgnored(): void
    {
        $this->config->set(OperatorConfig::PRIVACY_POLICY_URL, 'http://uni.example/datenschutz');
        $this->config->set(OperatorConfig::STORE_APP_STORE, 'https://apps.apple.com/app/id1');
        $this->config->set(OperatorConfig::STORE_GOOGLE_PLAY, 'javascript:alert(1)');
        self::assertNull($this->settings->stringValue(Settings::PRIVACY_POLICY_URL));
        self::assertSame(
            ['appStore' => 'https://apps.apple.com/app/id1'],
            $this->settings->arrayValue(Settings::STORE_LINKS)
        );
    }

    public function testFeaturesMapToOneSwitchEach(): void
    {
        $this->config->set(OperatorConfig::FEATURE_BLUBBER, true);
        self::assertSame(
            ['courseCatalogue' => false, 'blubber' => true, 'mensa' => false, 'messages' => true, 'eventPlaces' => true, 'courseware' => true, 'webLogin' => true, 'icalKey' => true],
            $this->settings->arrayValue(Settings::FEATURES)
        );
    }

    public function testWebLoginIsOnByDefaultAndCanBeSwitchedOff(): void
    {
        $this->config->set(OperatorConfig::ENABLED, true);
        self::assertFalse($this->settings->isWebLoginEnabled(), 'needs the app client');
        $this->table->set(Settings::OAUTH_CLIENT_ID, '7');
        self::assertTrue($this->settings->isWebLoginEnabled());
        $this->config->set(OperatorConfig::FEATURE_WEB_LOGIN, false);
        self::assertFalse($this->settings->isWebLoginEnabled());
        $this->config->set(OperatorConfig::FEATURE_WEB_LOGIN, true);
        $this->config->set(OperatorConfig::ENABLED, false);
        self::assertFalse($this->settings->isWebLoginEnabled());
    }

    public function testWritingOperatorSettingsFillsTheConfiguration(): void
    {
        $this->settings->set(Settings::INSTANCE, ['name' => 'Uni', 'shortName' => 'U']);
        $this->settings->set(Settings::STORE_LINKS, ['googlePlay' => 'https://play.google.com/store/apps/details?id=x']);
        $this->settings->set(Settings::FEATURES, ['mensa' => true]);
        $this->settings->set(Settings::ENABLED, true);
        self::assertSame('Uni', $this->config->get(OperatorConfig::INSTANCE_NAME));
        self::assertSame('U', $this->config->get(OperatorConfig::INSTANCE_SHORT_NAME));
        self::assertSame('', $this->config->get(OperatorConfig::STORE_APP_STORE));
        self::assertTrue($this->config->get(OperatorConfig::FEATURE_MENSA));
        self::assertFalse($this->config->get(OperatorConfig::FEATURE_BLUBBER));
        self::assertTrue($this->config->get(OperatorConfig::ENABLED));
        self::assertNull($this->table->get(Settings::ENABLED));
    }

    public function testEveryOperatorFieldIsDefinedInTheSection(): void
    {
        $fields = array_merge(
            [OperatorConfig::ENABLED, OperatorConfig::PAIRING_ENABLED, OperatorConfig::PROFILE_ENTRY,
                OperatorConfig::INSTANCE_NAME, OperatorConfig::INSTANCE_SHORT_NAME, OperatorConfig::PRIVACY_POLICY_URL,
                OperatorConfig::ROOM_FINDER_PROPERTY, OperatorConfig::SUPPORT_EMAIL, OperatorConfig::LOGO_URL,
                OperatorConfig::ACCENT_COLOR, OperatorConfig::MENSA_OPENMENSA_IDS, OperatorConfig::MENSA_URL],
            array_values(OperatorConfig::LINK_FIELDS),
            array_values(OperatorConfig::STORE_FIELDS),
            array_values(OperatorConfig::FEATURE_FIELDS)
        );
        self::assertEqualsCanonicalizing($fields, array_keys(OperatorConfig::definitions()));
        foreach (OperatorConfig::definitions() as $definition) {
            self::assertContains($definition['type'], ['boolean', 'string']);
            self::assertNotSame('', $definition['description']);
            self::assertLessThanOrEqual(255, mb_strlen($definition['description']));
        }
    }

    public function testBrandingLinksAndSupportComeFromTheConfiguration(): void
    {
        $this->config->set(OperatorConfig::ACCENT_COLOR, '#1a2b3c');
        $this->config->set(OperatorConfig::LOGO_URL, 'http://uni.example/logo.png');
        $this->config->set(OperatorConfig::SUPPORT_EMAIL, ' support@uni.example ');
        $this->config->set(OperatorConfig::LINK_HOMEPAGE, 'https://uni.example/');
        $this->config->set(OperatorConfig::LINK_LIBRARY, 'ftp://uni.example/bib');
        $this->config->set(OperatorConfig::MENSA_OPENMENSA_IDS, '12, 34');
        $this->config->set(OperatorConfig::MENSA_URL, 'https://uni.example/mensa');

        self::assertSame(['accentColor' => '#1A2B3C'], $this->settings->arrayValue(Settings::BRANDING));
        self::assertSame('support@uni.example', $this->settings->arrayValue(Settings::INSTANCE)['supportEmail']);
        self::assertSame(
            ['external' => [['type' => 'homepage', 'url' => 'https://uni.example/']],
                'mensa' => ['provider' => 'openmensa', 'canteenIds' => [12, 34]]],
            $this->settings->arrayValue(Settings::LINKS)
        );

        $this->config->set(OperatorConfig::MENSA_OPENMENSA_IDS, '');
        self::assertSame(['provider' => 'link', 'url' => 'https://uni.example/mensa'], $this->settings->arrayValue(Settings::LINKS)['mensa']);
        $this->config->set(OperatorConfig::SUPPORT_EMAIL, 'not an address');
        self::assertArrayNotHasKey('supportEmail', $this->settings->arrayValue(Settings::INSTANCE));
    }

    public function testExistingBrandingAndLinksCanBeCarriedOver(): void
    {
        $this->settings->set(Settings::BRANDING, ['accentColor' => '#112233', 'logoUrl' => 'https://uni.example/l.png']);
        $this->settings->set(Settings::LINKS, [
            'external' => [['type' => 'itHelp', 'url' => 'https://uni.example/it'], ['type' => 'other', 'url' => 'https://x.example']],
            'mensa' => ['provider' => 'openmensa', 'canteenIds' => [7]],
        ]);
        self::assertSame('#112233', $this->config->get(OperatorConfig::ACCENT_COLOR));
        self::assertSame('https://uni.example/it', $this->config->get(OperatorConfig::LINK_IT_HELP));
        self::assertSame('7', $this->config->get(OperatorConfig::MENSA_OPENMENSA_IDS));
        self::assertNull($this->table->get(Settings::LINKS));
    }
}
