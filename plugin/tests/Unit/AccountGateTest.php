<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Account\AccountGate;
use PHPUnit\Framework\TestCase;

/** Pairing, refresh and web login share one account rule. */
final class AccountGateTest extends TestCase
{
    public function testAnUsableAccountMayUseTheApp(): void
    {
        foreach (AccountGate::ROLES as $perms) {
            self::assertTrue(AccountGate::allows('u1', 'name', false, false, '', $perms), $perms);
        }
    }

    public function testLockedExpiredOrNotActivatedAccountsMayNot(): void
    {
        self::assertFalse(AccountGate::allows('u1', 'name', true, false, '', 'autor'));
        self::assertFalse(AccountGate::allows('u1', 'name', false, true, '', 'autor'));
        self::assertFalse(AccountGate::allows('u1', 'name', false, false, 'abc', 'autor'));
    }

    public function testNobodyAndOtherRolesMayNot(): void
    {
        self::assertFalse(AccountGate::allows('nobody', 'nobody', false, false, '', 'autor'));
        self::assertFalse(AccountGate::allows('', 'name', false, false, '', 'autor'));
        self::assertFalse(AccountGate::allows('u1', '', false, false, '', 'autor'));
        self::assertFalse(AccountGate::allows('u1', 'name', false, false, '', 'user'));
    }

    /** Every place that hands out or renews access asks the gate. */
    public function testPairingRefreshAndWebLoginAskTheGate(): void
    {
        $dir = __DIR__ . '/../../lib/classes';
        foreach (['OAuth2/PairingGrant.php', 'OAuth2/LineageRefreshTokenGrant.php', 'WebLogin/WebSignIn.php'] as $file) {
            self::assertStringContainsString('AccountGate::mayUseApp(', (string) file_get_contents($dir . '/' . $file), $file);
        }
    }
}
