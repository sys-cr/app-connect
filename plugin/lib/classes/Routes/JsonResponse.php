<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use Psr\Http\Message\ResponseInterface as Response;

/** A JSON body with its status and content type, written the same way by every route. */
final class JsonResponse
{
    public const JSON = 'application/json; charset=utf-8';
    public const JSON_API = 'application/vnd.api+json';

    /**
     * @param array<mixed> $document
     * @param int $flags further json_encode flags, such as JSON_UNESCAPED_UNICODE
     */
    public static function write(
        Response $response,
        array $document,
        int $status = 200,
        string $contentType = self::JSON,
        int $flags = 0
    ): Response {
        $response->getBody()->write(json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | $flags));
        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', $contentType);
    }

    private function __construct()
    {
    }
}
