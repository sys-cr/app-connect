<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The discovery test vectors the apps test against must agree with the schema the
 * plugin is held to: every vector flagged schemaValid passes, the others fail.
 */
final class DiscoveryTestVectorsTest extends TestCase
{
    /** @return iterable<string, array{string, bool}> */
    public static function vectors(): iterable
    {
        $dir = dirname(__DIR__, 3) . '/contract/test-vectors/discovery';
        $index = json_decode((string) file_get_contents($dir . '/index.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($index['cases'] as $case) {
            yield $case['file'] => [$dir . '/' . $case['file'], $case['schemaValid']];
        }
    }

    #[DataProvider('vectors')]
    public function testVectorAgreesWithTheSchema(string $file, bool $schemaValid): void
    {
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($schemaValid, DiscoverySchema::errors($data) === '');
    }

    public function testTheSchemaItselfIsValidJson(): void
    {
        $schema = json_decode((string) file_get_contents(DiscoverySchema::path()), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('https://json-schema.org/draft/2020-12/schema', $schema['$schema']);
        self::assertFalse($schema['additionalProperties']);
    }
}
