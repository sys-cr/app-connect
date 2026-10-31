<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Discovery\DiscoveryBuilder;
use AppConnect\Discovery\InstanceInfo;
use AppConnect\Settings\InMemorySettingsStore;
use AppConnect\Settings\Settings;
use AppConnect\Tests\Contract\DiscoverySchema;
use PHPUnit\Framework\TestCase;

final class DiscoveryBuilderTest extends TestCase
{
    private function instance(string $locale = 'de_DE'): InstanceInfo
    {
        return new InstanceInfo('https://studip.example.org', 'Universität Musterstadt', '6.2.3', '0.1.0', $locale);
    }

    /** @param array<string, mixed> $values */
    private function build(array $values): array
    {
        $values += [Settings::ENABLED => true, Settings::OAUTH_CLIENT_ID => '7'];
        return (new DiscoveryBuilder())->build(new Settings(new InMemorySettingsStore($values)), $this->instance());
    }

    public function testMinimalSettingsGiveASchemaValidPayloadWithTheBaseFeatures(): void
    {
        $payload = $this->build([]);

        DiscoverySchema::assertValid($payload);
        self::assertSame('Universität Musterstadt', $payload['instance']['name']);
        self::assertSame('https://studip.example.org/jsonapi.php/v1', $payload['instance']['jsonApiUrl']);
        self::assertSame('7', $payload['oauth2']['clientId']);
        self::assertSame('https://studip.example.org/jsonapi.php/v1/app-connect/token', $payload['oauth2']['tokenEndpoint']);
        self::assertSame('S256', $payload['oauth2']['pkce']);
        foreach (DiscoveryBuilder::BASE_FEATURES as $id) {
            self::assertSame(['version' => 1], $payload['features'][$id]);
        }
        self::assertFalse($payload['features']['blubber']);
        self::assertSame(
            ['version' => 1, 'route' => 'app-connect/course-dates/places'],
            $payload['features']['eventPlaces']
        );
        self::assertSame(
            ['version' => 1, 'route' => 'app-connect/user-language'],
            $payload['features']['userLanguage']
        );
        self::assertSame(
            ['version' => 1, 'route' => 'app-connect/web-login'],
            $payload['features']['webLogin']
        );
        self::assertSame(
            ['version' => 1, 'route' => 'app-connect/user-events'],
            $payload['features']['userEvents']
        );
        self::assertArrayNotHasKey('pairing', $payload);
        self::assertArrayNotHasKey('branding', $payload);
    }

    public function testEveryEndpointIsOnTheInstanceHost(): void
    {
        $payload = $this->build([]);
        foreach (['authorizationEndpoint', 'tokenEndpoint'] as $key) {
            self::assertSame('studip.example.org', parse_url($payload['oauth2'][$key], PHP_URL_HOST));
        }
        self::assertSame('studip.example.org', parse_url($payload['instance']['jsonApiUrl'], PHP_URL_HOST));
    }

    public function testOperatorValuesArePublishedAfterValidation(): void
    {
        $payload = $this->build([
            Settings::INSTANCE => ['name' => '  Uni Beispiel ', 'shortName' => 'UB'],
            Settings::PAIRING_ENABLED => true,
            Settings::FEATURES => ['blubber' => true, 'courseCatalogue' => true, 'mensa' => true],
            Settings::BRANDING => ['accentColor' => '#28497C', 'gradient' => ['#28497C', '#6B8FC7'], 'logoUrl' => 'https://studip.example.org/logo.svg'],
            Settings::LINKS => [
                'external' => [['type' => 'library', 'label' => 'Bibliothek', 'url' => 'https://lib.example.org']],
                'mensa' => ['provider' => 'openmensa', 'canteenIds' => [7, 8]],
            ],
            Settings::PRIVACY_POLICY_URL => 'https://www.example.org/datenschutz',
            Settings::INTERVALS => ['sync' => ['backgroundIntervalMinutes' => 60], 'pollIntervals' => ['blubberChannel' => 20]],
            Settings::STATISTICS => ['matomoUrl' => 'https://stats.example.org/matomo.php', 'siteId' => '4'],
        ]);

        DiscoverySchema::assertValid($payload);
        self::assertSame('Uni Beispiel', $payload['instance']['name']);
        self::assertSame(['version' => 1], $payload['pairing']);
        self::assertSame(['version' => 1], $payload['features']['blubber']);
        self::assertSame(['version' => 1], $payload['features']['mensa']);
        self::assertSame(
            [
                ['type' => 'library', 'label' => 'Bibliothek', 'url' => 'https://lib.example.org'],
                ['type' => 'privacyPolicy', 'url' => 'https://www.example.org/datenschutz'],
            ],
            $payload['links']['external']
        );
        self::assertSame(['backgroundIntervalMinutes' => 60], $payload['sync']);
        self::assertSame(['blubberChannel' => 20], $payload['pollIntervals']);
    }

    public function testInvalidOptionalValuesAreDroppedNotPublished(): void
    {
        $payload = $this->build([
            Settings::FEATURES => ['mensa' => true, 'push' => true],
            Settings::BRANDING => ['accentColor' => 'red', 'gradient' => ['#000000', '#111111', '#222222', '#333333'], 'logoUrl' => 'http://x.example.org/l.png'],
            Settings::LINKS => ['external' => [
                ['type' => 'homepage', 'url' => 'javascript:alert(1)'],
                ['type' => 'unknown', 'url' => 'https://x.example.org'],
                ['type' => 'privacyPolicy', 'url' => 'https://twice.example.org'],
            ]],
            Settings::PRIVACY_POLICY_URL => 'http://insecure.example.org',
            Settings::INTERVALS => ['sync' => ['backgroundIntervalMinutes' => 5], 'pollIntervals' => ['blubberList' => 1000]],
            Settings::STATISTICS => ['matomoUrl' => 'https://stats.example.org/matomo.php'],
        ]);

        DiscoverySchema::assertValid($payload);
        // Mensa needs a data source; push is not an operator switch.
        self::assertFalse($payload['features']['mensa']);
        self::assertArrayNotHasKey('push', $payload['features']);
        foreach (['branding', 'links', 'sync', 'pollIntervals', 'statistics'] as $block) {
            self::assertArrayNotHasKey($block, $payload);
        }
    }

    public function testAnInvalidLocaleIsLeftOut(): void
    {
        $payload = (new DiscoveryBuilder())->build(
            new Settings(new InMemorySettingsStore([Settings::OAUTH_CLIENT_ID => '7'])),
            $this->instance('german')
        );

        self::assertArrayNotHasKey('locale', $payload['instance']);
    }

    public function testMessagesAndPlacesCanBeSwitchedOff(): void
    {
        $payload = $this->build([
            'enabled' => true,
            'oauth_client_id' => '2',
            'features' => ['messages' => false, 'eventPlaces' => false],
        ]);
        self::assertFalse($payload['features']['messages']);
        self::assertFalse($payload['features']['eventPlaces']);
        self::assertSame(['version' => 1], $payload['features']['courses']);
    }

    public function testWebLoginCanBeSwitchedOff(): void
    {
        $payload = $this->build([Settings::FEATURES => ['webLogin' => false]]);
        DiscoverySchema::assertValid($payload);
        self::assertFalse($payload['features']['webLogin']);
        self::assertSame(['version' => 1], $payload['features']['courses']);
    }

    public function testCoursewareFollowsTheModuleAndTheSwitch(): void
    {
        $settings = fn (array $features): Settings => new Settings(new InMemorySettingsStore([
            Settings::ENABLED => true, Settings::OAUTH_CLIENT_ID => '2', Settings::FEATURES => $features,
        ]));
        $withModule = new InstanceInfo('https://studip.example.org', 'Uni', '6.2.3', '0.1.0', 'de_DE', true);
        $builder = new DiscoveryBuilder();

        self::assertSame(['version' => 1], $builder->build($settings([]), $withModule)['features']['courseware']);
        self::assertFalse($builder->build($settings(['courseware' => false]), $withModule)['features']['courseware']);
        self::assertFalse($builder->build($settings([]), $this->instance())['features']['courseware']);
    }

    public function testAValidSupportAddressIsPublished(): void
    {
        $valid = $this->build(['instance' => ['supportEmail' => 'support@uni.example']]);
        self::assertSame('support@uni.example', $valid['instance']['supportEmail']);
        DiscoverySchema::assertValid($valid);
        $invalid = $this->build(['instance' => ['supportEmail' => 'support at uni']]);
        self::assertArrayNotHasKey('supportEmail', $invalid['instance']);
    }
}
