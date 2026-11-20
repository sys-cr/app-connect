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
        return JsonResponse::write(
            $response,
            ['errors' => [['status' => (string) $status, 'code' => $code, 'title' => $title]]],
            $status,
            JsonResponse::JSON_API
        );
    }
}
