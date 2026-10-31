<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\OAuth2\TokenServer;
use AppConnect\Runtime;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /jsonapi.php/v1/app-connect/token – the token endpoint the discovery
 * names (RFC 6749 § 3.2). It accepts the app client only: authorization_code
 * (browser sign-in), refresh_token (keeps the device binding on the current
 * pair) and the pairing grant.
 */
final class TokenCreate extends \JsonApi\NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isEnabled() || $settings->clientId() === null) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        $response = $response->withHeader('Cache-Control', 'no-store')->withHeader('Pragma', 'no-cache');

        $params = (array) $request->getParsedBody();
        if ((string) ($params['client_id'] ?? '') !== $settings->clientId()) {
            return OAuthServerException::invalidClient($request)->generateHttpResponse($response);
        }

        try {
            $address = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
            return TokenServer::create($settings, $address)->respondToAccessTokenRequest($request, $response);
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse($response);
        }
    }
}
