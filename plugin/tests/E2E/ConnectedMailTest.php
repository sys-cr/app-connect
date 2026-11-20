<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/**
 * The mail "Gerät verbunden" goes out after the token response, not within
 * it, and still arrives (#28). The instance writes mails to its debug log.
 */
final class ConnectedMailTest extends E2ETestCase
{
    public function testPairingSendsTheMailAfterTheResponse(): void
    {
        $before = count(self::helper('mails', self::AUTOR . '@example.org'));

        self::pair(self::AUTOR);

        $subjects = self::helper('mails', self::AUTOR . '@example.org');
        self::assertCount($before + 1, $subjects);
        self::assertSame('Gerät verbunden', end($subjects));
    }
}
