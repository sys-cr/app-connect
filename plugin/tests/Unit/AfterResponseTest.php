<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\AfterResponse;
use PHPUnit\Framework\TestCase;

/** Work after the response runs at the end of the request, not at once. */
final class AfterResponseTest extends TestCase
{
    public function testTheTaskRunsOnlyWhenTheRequestEnds(): void
    {
        $registered = [];
        $ran = 0;

        AfterResponse::run(static function () use (&$ran): void {
            $ran++;
        }, static function (\Closure $atShutdown) use (&$registered): void {
            $registered[] = $atShutdown;
        });

        self::assertSame(0, $ran);
        self::assertCount(1, $registered);
        $registered[0]();
        self::assertSame(1, $ran);
    }

    public function testTheConnectionMailIsSentAfterTheResponse(): void
    {
        foreach (['lib/classes/OAuth2/TokenServer.php', 'lib/classes/Routes/DevicesCreate.php'] as $file) {
            $source = (string) file_get_contents(__DIR__ . '/../../' . $file);
            self::assertStringContainsString('AfterResponse::run(static fn () => ConnectedMail::send(', $source, $file);
        }
    }
}
