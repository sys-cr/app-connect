<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Contract;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Assert;

/**
 * Validates data against contract/discovery.schema.json.
 */
final class DiscoverySchema
{
    public static function path(): string
    {
        return dirname(__DIR__, 3) . '/contract/discovery.schema.json';
    }

    /** Returns the validation errors as text; empty when valid. */
    public static function errors(mixed $data): string
    {
        $validator = new Validator();
        $validator->setMaxErrors(10);
        $schema = json_decode((string) file_get_contents(self::path()), false, 512, JSON_THROW_ON_ERROR);
        $document = json_decode(json_encode($data, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $result = $validator->validate($document, $schema);
        if ($result->isValid()) {
            return '';
        }
        return json_encode((new ErrorFormatter())->format($result->error()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function assertValid(mixed $data): void
    {
        $errors = self::errors($data);
        Assert::assertSame('', $errors, "Discovery payload violates the schema:\n" . $errors);
    }
}
