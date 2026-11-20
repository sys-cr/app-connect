<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Every route checks availability the same way (contract/route-access.md). */
final class RouteAccessTest extends TestCase
{
    public function testEveryRouteNeedsTheSwitchAndTheClient(): void
    {
        $routes = glob(__DIR__ . '/../../lib/classes/Routes/*.php') ?: [];
        self::assertNotEmpty($routes);
        foreach ($routes as $file) {
            $source = (string) file_get_contents($file);
            if (!str_contains($source, 'extends Route')) {
                continue;
            }
            self::assertStringContainsString('->isConnected()', $source, basename($file));
            self::assertStringNotContainsString('->isEnabled()', $source, basename($file));
        }
    }

    public function testTheContractNamesEveryRoute(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../contract/route-access.md');
        foreach (['discovery', 'logo', 'token', 'revoke', 'devices', 'me/ical-key', 'web-login', 'user-events', 'course-dates/places', 'user-language'] as $route) {
            self::assertStringContainsString(' ' . $route . '`', $contract, $route);
        }
    }
}
