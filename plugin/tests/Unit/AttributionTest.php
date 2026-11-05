<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Attribution;
use PHPUnit\Framework\TestCase;

/**
 * The additional terms require the user interface to name author, source
 * repository and license. These tests keep the values and the help-bar notice
 * on the pages people use from getting lost.
 */
final class AttributionTest extends TestCase
{
    private const PLUGIN_DIR = __DIR__ . '/../..';
    private const REPO_DIR = self::PLUGIN_DIR . '/..';

    public function testValues(): void
    {
        self::assertSame('Bodo Steffen', Attribution::AUTHOR);
        self::assertSame('https://github.com/sys-cr/app-connect', Attribution::SOURCE_URL);
        self::assertSame('GPL-3.0-or-later', Attribution::LICENSE);
        self::assertSame('SUPPLEMENTAL-TERMS.txt', Attribution::TERMS_FILE);
    }

    public function testComposerDeclaresTheSameLicense(): void
    {
        $composer = json_decode((string) file_get_contents(self::PLUGIN_DIR . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Attribution::LICENSE, $composer['license']);
    }

    public function testNoticeNamesAuthorSourceAndLicense(): void
    {
        require_once __DIR__ . '/../Support/studip-view-functions.php';

        $html = Attribution::html('<img alt="">');

        self::assertStringContainsString('Bodo Steffen', $html);
        self::assertStringContainsString('Abschnitt 7', $html);
        self::assertStringContainsString('href="https://github.com/sys-cr/app-connect"', $html);
        self::assertStringContainsString('href="https://www.gnu.org/licenses/gpl-3.0.html"', $html);
        self::assertSame(2, substr_count($html, '<img alt=""> '));
    }

    /** A fixed colour keeps the contrast measurable; opacity would also dim forced colours. */
    public function testNoticeUsesAFixedColourPerPlace(): void
    {
        require_once __DIR__ . '/../Support/studip-view-functions.php';

        $helpbar = Attribution::html();
        $page = Attribution::html('', false);

        self::assertStringNotContainsString('opacity', $helpbar . $page);
        self::assertStringContainsString('color: ' . Attribution::HELPBAR_COLOR . ';', $helpbar);
        self::assertStringContainsString('color: ' . Attribution::PAGE_COLOR . ';', $page);
        self::assertStringContainsString('var(--color--font-inverted) 85%, var(--color--brand-primary)', Attribution::HELPBAR_COLOR);
    }

    /** The pages people use show help and notice; the operator page does not need to. */
    public function testUserPagesAddHelpAndNoticeToTheHelpBar(): void
    {
        foreach (['connect', 'get'] as $controller) {
            $source = (string) file_get_contents(self::PLUGIN_DIR . "/controllers/{$controller}.php");

            self::assertMatchesRegularExpression('/Help::addToHelpbar\(Help::[A-Z]+/', $source, $controller);
            self::assertStringContainsString('Attribution::addToHelpbar();', $source, $controller);
        }
    }

    /** The core hides the help bar from visitors who are not signed in; "App holen" shows them the notice itself. */
    public function testGetPageShowsTheNoticeWithoutTheHelpBar(): void
    {
        $controller = (string) file_get_contents(self::PLUGIN_DIR . '/controllers/get.php');
        $view = (string) file_get_contents(self::PLUGIN_DIR . '/views/get/index.php');

        self::assertStringContainsString("have_perm('autor')", $controller);
        self::assertStringContainsString(': Attribution::html(', $controller);
        self::assertMatchesRegularExpression("/'alt' => ''\]\), false\);/", $controller);
        self::assertStringContainsString('<?= $attribution ?>', $view);
    }

    /** The plugin directory is shipped on its own, so it carries both texts. */
    public function testPluginShipsLicenseAndAdditionalTerms(): void
    {
        self::assertFileEquals(self::REPO_DIR . '/LICENSES/GPL-3.0-or-later.txt', self::PLUGIN_DIR . '/LICENSE');
        self::assertFileEquals(self::REPO_DIR . '/' . Attribution::TERMS_FILE, self::PLUGIN_DIR . '/' . Attribution::TERMS_FILE);
    }

    public function testAdditionalTermsStopAtTheStudipCore(): void
    {
        $terms = (string) file_get_contents(self::PLUGIN_DIR . '/' . Attribution::TERMS_FILE);

        self::assertStringContainsString('Section 7(b)', $terms);
        self::assertStringContainsString('do not extend to the underlying Stud.IP core system', $terms);
    }
}
