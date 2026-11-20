<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The controllers hand their values to the views as dynamic properties,
 * which PHP 8.2 and later deprecate unless the class allows them.
 */
final class ControllerTest extends TestCase
{
    public function testEveryControllerAllowsDynamicProperties(): void
    {
        $files = glob(__DIR__ . '/../../controllers/*.php') ?: [];
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            self::assertMatchesRegularExpression(
                '/#\[\\\\AllowDynamicProperties\]\s*\nclass \w+Controller extends/',
                $source,
                basename($file) . ' allows dynamic properties'
            );
        }
    }

    public function testNoControllerDeclaresTheValuesOfItsView(): void
    {
        foreach (glob(__DIR__ . '/../../controllers/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression(
                '/^\s*(public|protected)\s+(?!function|static|const)[^;(]*\$\w+\s*(=|;)/m',
                $source,
                basename($file) . ': a declared property would not reach the view'
            );
        }
    }
}
