<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Logo\LogoImage;
use AppConnect\Logo\LogoRejected;
use AppConnect\Tests\Support\FakeHttpFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogoImageTest extends TestCase
{
    /** @return iterable<string, array{string, string, int, int}> */
    public static function acceptedPictures(): iterable
    {
        yield 'PNG' => ['logo.png', 'image/png', 64, 48];
        yield 'JPEG' => ['logo.jpg', 'image/jpeg', 64, 64];
        yield 'WebP' => ['logo.webp', 'image/webp', 64, 64];
    }

    #[DataProvider('acceptedPictures')]
    public function testRasterPicturesAreRecognisedByTheirBytes(string $file, string $type, int $width, int $height): void
    {
        $bytes = FakeHttpFetcher::fixture($file);
        $image = LogoImage::fromBytes($bytes);

        self::assertSame($type, $image->mimeType);
        self::assertSame([$width, $height], [$image->width, $image->height]);
        self::assertSame(hash('sha256', $bytes), $image->hash);
        self::assertSame(substr($image->hash, 0, 16), $image->version());
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusedPictures(): iterable
    {
        yield 'SVG, even with an image extension' => [FakeHttpFetcher::fixture('logo.svg'), LogoRejected::TYPE];
        yield 'HTML' => ['<!doctype html><title>logo</title>', LogoRejected::TYPE];
        yield 'GIF' => ["GIF89a\x01\x00\x01\x00\x00\x00\x00;", LogoRejected::TYPE];
        yield 'empty' => ['', LogoRejected::TYPE];
        yield 'PNG signature without a header' => ["\x89PNG\r\n\x1a\n", LogoRejected::BROKEN];
        yield 'garbage after a PNG signature' => ["\x89PNG\r\n\x1a\n" . str_repeat('x', 64), LogoRejected::DIMENSIONS];
        yield 'JPEG signature on a PNG body' => ["\xFF\xD8\xFF" . substr(FakeHttpFetcher::fixture('logo.png'), 3), LogoRejected::BROKEN];
        yield 'too small' => [FakeHttpFetcher::fixture('too-small.png'), LogoRejected::DIMENSIONS];
        yield 'too wide' => [FakeHttpFetcher::fixture('too-wide.png'), LogoRejected::DIMENSIONS];
        yield 'over 512 KB' => [
            FakeHttpFetcher::fixture('logo.png') . str_repeat("\0", LogoImage::MAX_BYTES),
            LogoRejected::TOO_LARGE,
        ];
    }

    #[DataProvider('refusedPictures')]
    public function testEverythingElseIsRefusedWithItsReason(string $bytes, string $reason): void
    {
        try {
            LogoImage::fromBytes($bytes);
            self::fail('accepted');
        } catch (LogoRejected $rejected) {
            self::assertSame($reason, $rejected->reason);
        }
    }

    public function testExactly512KbIsAllowed(): void
    {
        $png = FakeHttpFetcher::fixture('logo.png');
        // Bytes after the end of a PNG do not change what getimagesize reads.
        $image = LogoImage::fromBytes($png . str_repeat("\0", LogoImage::MAX_BYTES - strlen($png)));

        self::assertSame(LogoImage::MAX_BYTES, strlen($image->bytes));
    }
}
