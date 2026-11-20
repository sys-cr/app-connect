<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The pages load their scripts from assets/js, so a strict CSP needs no 'unsafe-inline' (#32). */
final class ScriptsTest extends TestCase
{
    private const PLUGIN = __DIR__ . '/../..';

    public function testNoViewHasAnInlineScript(): void
    {
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::PLUGIN . '/views', \FilesystemIterator::SKIP_DOTS));
        foreach ($views as $view) {
            self::assertStringNotContainsString('<script', (string) file_get_contents($view->getPathname()), $view->getFilename());
        }
    }

    public function testEveryLoadedScriptExists(): void
    {
        $loaded = [];
        foreach (glob(self::PLUGIN . '/controllers/*.php') ?: [] as $controller) {
            preg_match_all("/addPageScript\\('([\\w-]+\\.js)'\\)/", (string) file_get_contents($controller), $matches);
            array_push($loaded, ...$matches[1]);
        }

        sort($loaded);
        $files = array_map('basename', glob(self::PLUGIN . '/assets/js/*.js') ?: []);
        sort($files);
        self::assertSame(['connect.js', 'get.js', 'web-enter.js'], $loaded);
        self::assertSame($loaded, $files);
    }
}
