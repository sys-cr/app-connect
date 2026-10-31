<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\WebLogin\ReturnPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReturnPathTest extends TestCase
{
    private const BASE = 'https://studip.example.org/studip/';

    /** @return iterable<string, array{string}> */
    public static function acceptedPaths(): iterable
    {
        foreach ([
            'dispatch.php/start',
            'dispatch.php/course/details/index?cid=0123456789abcdef0123456789abcdef',
            'dispatch.php/course/courseware/courseware/1?cid=0123456789abcdef0123456789abcdef#/structural_element/7',
            'dispatch.php/course/enrolment/apply/0123456789abcdef0123456789abcdef',
            'dispatch.php/search/globalsearch?q=%C3%A4rger&x[]=1',
            'plugins.php/coreforum/index?cid=abc',
            'index.php',
            'index.php?again=yes',
            'index.php#top',
            'seminar_main.php?auswahl=0123456789abcdef0123456789abcdef',
            'dispatch.php/files/index/abc?from=list.v2',
        ] as $path) {
            yield $path => [$path];
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function hostileValues(): iterable
    {
        $cases = [
            'absolute https' => 'https://evil.example/',
            'absolute http' => 'http://evil.example/dispatch.php/start',
            'scheme without slashes' => 'https:evil.example',
            'javascript' => 'javascript:alert(1)',
            'javascript mixed case' => 'JaVaScRiPt:alert(1)',
            'data' => 'data:text/html,<script>alert(1)</script>',
            'protocol relative' => '//evil.example/dispatch.php/start',
            'root relative' => '/dispatch.php/start',
            'backslash host' => '/\\evil.example',
            'double backslash' => '\\\\evil.example',
            'backslash in path' => 'dispatch.php/start\\..\\x',
            'encoded backslash' => 'dispatch.php/start%5Cevil',
            'double slash in path' => 'dispatch.php//evil.example',
            'double slash in query' => 'dispatch.php/start?next=https://evil.example',
            'encoded double slash' => 'dispatch.php/%2F%2Fevil.example',
            'encoded double slash in query' => 'dispatch.php/start?next=https%3A%2F%2Fevil.example',
            'double encoded double slash' => 'dispatch.php/start?next=%252F%252Fevil.example',
            'dot dot' => 'dispatch.php/../../etc/passwd',
            'trailing dot dot' => 'dispatch.php/course/..',
            'single dot' => 'plugins.php/./x',
            'encoded dot dot' => 'dispatch.php/%2e%2e/%2e%2e/admin.php',
            'encoded dot dot upper' => 'dispatch.php/%2E%2E/admin.php',
            'half encoded dot dot' => 'dispatch.php/.%2e/admin.php',
            'double encoded dot dot' => 'dispatch.php/%252e%252e/admin.php',
            'triple encoded dot dot' => 'dispatch.php/%25252e%25252e/admin.php',
            'dot dot with encoded slash' => 'dispatch.php/x/..%2f..%2fadmin.php',
            'dot dot in fragment' => 'dispatch.php/start#/../x',
            'deeply nested encoding' => 'dispatch.php/x%252525252525252Fy',
            'carriage return' => "dispatch.php/start\r\nLocation: https://evil.example",
            'encoded crlf' => 'dispatch.php/start%0d%0aSet-Cookie:%20a=b',
            'nul byte' => "dispatch.php/start\0",
            'encoded nul byte' => 'dispatch.php/start%00',
            'tab' => "dispatch.php/\tstart",
            'space' => 'dispatch.php/my courses',
            'leading space' => ' dispatch.php/start',
            'quote' => 'dispatch.php/start"onmouseover="x',
            'angle brackets' => 'dispatch.php/<script>',
            'raw non ascii' => 'dispatch.php/ärger',
            'malformed escape' => 'dispatch.php/%zz',
            'lone percent' => 'dispatch.php/100%',
            'two fragments' => 'dispatch.php/start#a#b',
            'unknown script' => 'admin.php',
            'migration page' => 'web_migrate.php',
            'prefix without slash' => 'dispatch.php',
            'plugins without slash' => 'plugins.php',
            'upper case prefix' => 'DISPATCH.PHP/start',
            'index lookalike host' => 'index.php.evil.example',
            'index with suffix' => 'index.phpx',
            'index with path info' => 'index.php/evil',
            'seminar_main userinfo' => 'seminar_main.php@evil.example',
            'empty' => '',
            'too long' => 'dispatch.php/' . str_repeat('a', ReturnPath::MAX_LENGTH),
        ];
        foreach ($cases as $name => $value) {
            yield $name => [$value];
        }
        yield 'number' => [42];
        yield 'array' => [['dispatch.php/start']];
        yield 'null' => [null];
        yield 'bool' => [true];
    }

    #[DataProvider('acceptedPaths')]
    public function testPathsOnThisInstanceAreAccepted(string $path): void
    {
        self::assertSame($path, ReturnPath::parse($path));
        $url = ReturnPath::absoluteUrl(self::BASE, $path);
        self::assertStringStartsWith(self::BASE, $url);
        self::assertSame('studip.example.org', parse_url($url, PHP_URL_HOST));
        self::assertNull(parse_url($url, PHP_URL_USER));
    }

    #[DataProvider('hostileValues')]
    public function testHostileValuesAreRefused(mixed $value): void
    {
        self::assertNull(ReturnPath::parse($value));
    }

    public function testTheAddressIsBuiltOnTheBaseUrlWithOrWithoutTrailingSlash(): void
    {
        self::assertSame(
            'https://studip.example.org/dispatch.php/start',
            ReturnPath::absoluteUrl('https://studip.example.org', 'dispatch.php/start')
        );
        self::assertSame(
            'https://studip.example.org/dispatch.php/start',
            ReturnPath::absoluteUrl('https://studip.example.org/', 'dispatch.php/start')
        );
    }
}
