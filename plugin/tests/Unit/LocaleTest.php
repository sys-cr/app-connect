<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every German text the plugin translates (`$this->_()`, `$_()`, `$_n()`,
 * `Translation::text()`) has an English entry in locale/en, and the compiled
 * catalogue carries it.
 */
final class LocaleTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';
    private const PO = self::ROOT . '/locale/en/LC_MESSAGES/app-connect.po';
    private const MO = self::ROOT . '/locale/en/LC_MESSAGES/app-connect.mo';

    public function testEveryTextHasAnEnglishTranslation(): void
    {
        $catalogue = self::readPo((string) file_get_contents(self::PO));
        $missing = array_diff(self::sourceTexts(), array_keys(array_filter($catalogue)));
        self::assertSame([], array_values($missing));
    }

    public function testTheCatalogueHasNoUnusedEntries(): void
    {
        $unused = array_diff(array_keys(self::readPo((string) file_get_contents(self::PO))), self::sourceTexts());
        self::assertSame([], array_values($unused));
    }

    public function testTheCompiledCatalogueIsCurrent(): void
    {
        $mo = (string) file_get_contents(self::MO);
        foreach (self::readPo((string) file_get_contents(self::PO)) as $msgid => $msgstr) {
            self::assertStringContainsString($msgid . "\0", $mo, 'make locale');
            self::assertStringContainsString($msgstr, $mo, 'make locale');
        }
    }

    /** @return list<string> the translatable literals in the plugin's PHP files */
    private static function sourceTexts(): array
    {
        $texts = [];
        foreach (['AppConnect.php', 'controllers', 'views', 'lib', 'cronjobs'] as $path) {
            $path = self::ROOT . '/' . $path;
            $files = is_dir($path)
                ? iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)))
                : [new \SplFileInfo($path)];
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    array_push($texts, ...self::textsIn((string) file_get_contents($file->getPathname())));
                }
            }
        }
        return array_values(array_unique($texts));
    }

    /**
     * Literal arguments of the translation calls, with adjacent literals
     * joined by `.` read as one text; for `$_n()` both forms.
     *
     * @return list<string>
     */
    private static function textsIn(string $source): array
    {
        $tokens = array_values(array_filter(
            token_get_all($source),
            static fn ($token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT], true)
        ));
        $texts = [];
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }
            $isCall = ($token[0] === T_STRING && in_array($token[1], ['_', 'text'], true)
                    && in_array(self::text($tokens[$i - 1] ?? ''), ['->', '::'], true))
                || ($token[0] === T_VARIABLE && in_array($token[1], ['$_', '$_n'], true));
            if (!$isCall || ($tokens[$i + 1] ?? null) !== '(') {
                continue;
            }
            $arguments = $token[1] === '$_n' ? 2 : 1;
            $j = $i + 2;
            for ($n = 0; $n < $arguments; $n++) {
                $text = null;
                while (is_array($tokens[$j] ?? null) && $tokens[$j][0] === T_CONSTANT_ENCAPSED_STRING) {
                    $text = ($text ?? '') . eval('return ' . $tokens[$j][1] . ';');
                    $j++;
                    if (($tokens[$j] ?? null) !== '.') {
                        break;
                    }
                    $j++;
                }
                if ($text !== null) {
                    $texts[] = $text;
                }
                $j++;
            }
        }
        return $texts;
    }

    private static function text(mixed $token): string
    {
        return is_array($token) ? $token[1] : (string) $token;
    }

    /** @return array<string, string> msgid => msgstr, without the header */
    private static function readPo(string $po): array
    {
        $entries = [];
        preg_match_all('/^msgid ((?:".*"\n)+)msgstr ((?:".*"\n?)+)/m', $po, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $msgid = self::poString($match[1]);
            if ($msgid !== '') {
                $entries[$msgid] = self::poString($match[2]);
            }
        }
        return $entries;
    }

    private static function poString(string $lines): string
    {
        preg_match_all('/^"(.*)"$/m', $lines, $parts);
        return stripcslashes(implode('', $parts[1]));
    }
}
