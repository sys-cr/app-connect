<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Logo\LogoImage;
use AppConnect\Logo\LogoResponse;
use AppConnect\Tests\Support\FakeHttpFetcher;
use PHPUnit\Framework\TestCase;

final class LogoResponseTest extends TestCase
{
    private LogoImage $image;

    protected function setUp(): void
    {
        $this->image = LogoImage::fromBytes(FakeHttpFetcher::fixture('logo.png'));
    }

    public function testThePictureIsServedWithItsCheckedTypeAndSafeHeaders(): void
    {
        $answer = LogoResponse::for($this->image, null);

        self::assertSame(200, $answer->status);
        self::assertSame($this->image->bytes, $answer->body);
        self::assertSame([
            'ETag' => '"' . hash('sha256', $this->image->bytes) . '"',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($this->image->bytes),
        ], $answer->headers);
    }

    public function testAKnownEntityTagGetsA304(): void
    {
        $etag = '"' . $this->image->hash . '"';
        foreach ([$etag, 'W/' . $etag, '"other", ' . $etag, '*'] as $ifNoneMatch) {
            $answer = LogoResponse::for($this->image, $ifNoneMatch);
            self::assertSame(304, $answer->status, $ifNoneMatch);
            self::assertSame('', $answer->body);
            self::assertSame($etag, $answer->headers['ETag']);
            self::assertSame('public, max-age=86400', $answer->headers['Cache-Control']);
            self::assertArrayNotHasKey('Content-Type', $answer->headers);
        }
        self::assertSame(200, LogoResponse::for($this->image, '"other"')->status);
        self::assertSame(200, LogoResponse::for($this->image, '')->status);
    }

    public function testWithoutAValidCopyTheAnswerIs404(): void
    {
        $answer = LogoResponse::for(null, '*');

        self::assertSame(404, $answer->status);
        self::assertSame('', $answer->body);
    }
}
