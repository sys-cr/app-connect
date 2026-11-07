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
        self::assertSame('GPL-3.0-or-later', Attribution::LICENSE);
        self::assertSame('SUPPLEMENTAL-TERMS.txt', Attribution::TERMS_FILE);
    }

    public function testComposerDeclaresTheSameLicense(): void
    {
        $composer = json_decode((string) file_get_contents(self::PLUGIN_DIR . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Attribution::LICENSE, $composer['license']);
    }

    /** The notice names author, copyright and license with a link to the license, and no source link. */
    public function testNoticeNamesAuthorAndLicense(): void
    {
        require_once __DIR__ . '/../Support/studip-view-functions.php';

        $html = Attribution::html('<img alt="">');

        self::assertStringContainsString('app-connect wurde von Bodo Steffen', $html);
        self::assertStringContainsString('© ' . Attribution::COPYRIGHT_YEAR, $html);
        self::assertStringContainsString('Abschnitt 7', $html);
        self::assertStringContainsString('GNU GPL 3.0 oder neuer', $html);
        self::assertStringContainsString('href="https://www.gnu.org/licenses/gpl-3.0.html"', $html);
        self::assertStringNotContainsString('github.com', $html);
        self::assertSame(1, substr_count($html, '<img alt=""> '));
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

    /** The notice stays readable: 14 px in the help bar and in the page. */
    public function testNoticeIsSetAtFourteenPixels(): void
    {
        require_once __DIR__ . '/../Support/studip-view-functions.php';

        foreach ([Attribution::html(), Attribution::html('', false)] as $html) {
            // 14 px at 1.75 keeps stacked links 24.5 px apart (WCAG 2.5.8).
            self::assertStringContainsString('font-size: 14px; line-height: 1.75;', $html);
            self::assertStringNotContainsString('em;', $html);
            // The link never breaks inside.
            self::assertSame(1, substr_count($html, 'white-space: nowrap;'));
        }
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

        self::assertStringContainsString('do not extend to the Stud.IP core system', $terms);
    }

    /** The terms stay within section 7: attribution in the legal notices, no required link. */
    public function testAdditionalTermsKeepTheAttributionInTheLegalNotices(): void
    {
        $terms = (string) file_get_contents(self::PLUGIN_DIR . '/' . Attribution::TERMS_FILE);

        self::assertStringContainsString('Author attribution (Section 7(b))', $terms);
        self::assertStringContainsString('"app-connect by Bodo Steffen"', $terms);
        self::assertStringContainsString('Appropriate Legal', $terms);
        self::assertStringNotContainsString('github.com', $terms);
        self::assertStringContainsString('Severability', $terms);
        self::assertStringContainsString('No particular screen, position or design is required.', $terms);
    }
}
