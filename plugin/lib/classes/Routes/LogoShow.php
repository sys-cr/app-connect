<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Logo\LogoResponse;
use AppConnect\Runtime;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * GET /jsonapi.php/v1/app-connect/logo – the plugin's copy of the
 * institution logo, without sign-in like the discovery, which names this
 * address as `branding.logoUrl`. Answers 404 `app-connect-no-logo` while
 * there is no valid copy for the configured address, and 404
 * `app-connect-disabled` while the app connection is switched off.
 */
final class LogoShow extends \JsonApi\NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isEnabled() || $settings->clientId() === null) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        $answer = LogoResponse::for(
            Runtime::logo()->current(Runtime::configuredLogoUrl()),
            $request->getHeaderLine('If-None-Match')
        );
        if ($answer->status === 404) {
            return JsonErrors::write($response, 404, 'app-connect-no-logo', 'No logo is stored');
        }
        foreach ($answer->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        $response->getBody()->write($answer->body);
        return $response->withStatus($answer->status);
    }
}
