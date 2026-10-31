<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Discovery\DiscoveryBuilder;
use AppConnect\Discovery\DiscoveryResponse;
use AppConnect\Runtime;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /jsonapi.php/v1/app-connect/discovery – public instance data as plain
 * JSON, with ETag and 304. Answers 404 `app-connect-disabled` while the app
 * connection is switched off.
 */
final class DiscoveryShow extends \JsonApi\NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isEnabled() || $settings->clientId() === null) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }

        $discovery = new DiscoveryResponse((new DiscoveryBuilder())->build($settings, Runtime::instanceInfo()));
        $response = $response
            ->withHeader('ETag', $discovery->etag)
            ->withHeader('Cache-Control', DiscoveryResponse::CACHE_CONTROL);
        if ($discovery->isNotModified($request->getHeaderLine('If-None-Match'))) {
            return $response->withStatus(304);
        }
        $response->getBody()->write($discovery->body);
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
