<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The page title of the core is the page's only h1; the headings of the
 * plugin's content boxes are h2 with the core's content box style.
 */
final class ContentBoxTest extends TestCase
{
    private const VIEWS = __DIR__ . '/../../views';

    public function testViewsUseStyledH2Headings(): void
    {
        foreach (['connect/index.php', 'get/index.php'] as $view) {
            $source = (string) file_get_contents(self::VIEWS . '/' . $view);

            self::assertStringNotContainsString('<h1', $source, $view);
            self::assertSame(
                substr_count($source, '<header><h2'),
                substr_count($source, '<header><h2 style="<?= \AppConnect\ContentBox::HEADING_STYLE ?>">'),
                $view
            );
            self::assertGreaterThan(0, substr_count($source, '<header><h2'), $view);
        }
    }
}
