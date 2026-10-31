<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * JSON:API error documents with an application-specific error code.
 */
final class JsonErrors
{
    public static function write(Response $response, int $status, string $code, string $title): Response
    {
        $body = json_encode(
            ['errors' => [['status' => (string) $status, 'code' => $code, 'title' => $title]]],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
        $response->getBody()->write($body);
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/vnd.api+json');
    }
}
