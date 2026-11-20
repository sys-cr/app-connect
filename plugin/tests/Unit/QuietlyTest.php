<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Logo\AddressGuard;
use AppConnect\Quietly;
use PHPUnit\Framework\TestCase;

/** Warnings of a call are ignored, its result and its exceptions are not (#34). */
final class QuietlyTest extends TestCase
{
    public function testAWarningOfTheCallIsIgnoredAndItsResultReturned(): void
    {
        // file_get_contents() warns besides returning false.
        self::assertFalse(Quietly::call(static fn () => file_get_contents(__DIR__ . '/does-not-exist')));
        self::assertSame('ok', Quietly::call(static fn () => 'ok'));
    }

    public function testExceptionsPass(): void
    {
        $this->expectException(\RuntimeException::class);
        Quietly::call(static fn () => throw new \RuntimeException('passes'));
    }

    public function testTheHandlerIsRestored(): void
    {
        $before = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        Quietly::call(static fn () => null);
        $after = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        self::assertSame($before, $after);
    }

    public function testNoSuppressionOperatorIsLeft(): void
    {
        foreach (['lib', 'controllers'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $code = array_filter(token_get_all((string) file_get_contents($file->getPathname())), static fn ($token): bool => $token === '@');
                self::assertSame([], $code, $file->getFilename());
            }
        }
    }

    public function testInvalidAddressesAreNotPublic(): void
    {
        self::assertFalse(AddressGuard::isPublic('not-an-address'));
        self::assertFalse(AddressGuard::isPublic('fe80::1%eth0'));
        self::assertTrue(AddressGuard::isPublic('93.184.215.14'));
    }
}
