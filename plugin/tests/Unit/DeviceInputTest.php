<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Devices\DeviceInput;
use PHPUnit\Framework\TestCase;

final class DeviceInputTest extends TestCase
{
    private const UUID = '3F2504E0-4F89-41D3-9A0C-0305E82C3301';

    public function testValidInputIsNormalised(): void
    {
        $device = DeviceInput::fromValues(self::UUID, "  Ada's iPhone\n", 'ios');

        self::assertNotNull($device);
        self::assertSame(strtolower(self::UUID), $device->deviceId);
        self::assertSame("Ada's iPhone", $device->name);
        self::assertSame('ios', $device->platform);
    }

    public function testControlCharactersInTheNameBecomeSpaces(): void
    {
        self::assertSame('Pixel 8', DeviceInput::fromValues(self::UUID, "Pixel\t8", 'android')?->name);
    }

    public function testInvalidInputIsRejected(): void
    {
        self::assertNull(DeviceInput::fromValues('not-a-uuid', 'Phone', 'ios'));
        self::assertNull(DeviceInput::fromValues(self::UUID, '', 'ios'));
        self::assertNull(DeviceInput::fromValues(self::UUID, str_repeat('x', 65), 'ios'));
        self::assertNull(DeviceInput::fromValues(self::UUID, 'Phone', 'windows'));
        self::assertNull(DeviceInput::fromValues(self::UUID, 'Phone', 'IOS'));
        self::assertNull(DeviceInput::fromValues(null, 'Phone', 'ios'));
        self::assertNull(DeviceInput::fromValues(self::UUID, ['Phone'], 'ios'));
    }

    public function testNamesUpTo64CharactersAreKept(): void
    {
        $name = str_repeat('ä', 64);
        self::assertSame($name, DeviceInput::fromValues(self::UUID, $name, 'android')?->name);
    }
}
