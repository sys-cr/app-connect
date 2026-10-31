<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Accepts only an OAuth2 token of the app client for the signed-in person.
 * The JSON:API also lets sessions, HTTP Basic and tokens of other OAuth2
 * clients through; routes that hand out lasting access use this check, so
 * a third-party token never turns into access that outlives it.
 */
final class AppToken
{
    /** Null when the request carries the app's token for $userId, else the error response. */
    public static function refusal(Request $request, Response $response, string $clientId, string $userId): ?Response
    {
        try {
            $validated = (new \Studip\OAuth2\Container())->get(ResourceServer::class)->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $exception) {
            return JsonErrors::write($response, 401, 'app-connect-token-required', 'An OAuth2 token of the app is required');
        }
        if ((string) $validated->getAttribute('oauth_client_id') !== $clientId) {
            return JsonErrors::write($response, 403, 'app-connect-wrong-client', 'The token does not belong to the app');
        }
        if ((string) $validated->getAttribute('oauth_user_id') !== $userId) {
            return JsonErrors::write($response, 401, 'app-connect-token-required', 'An OAuth2 token of the app is required');
        }
        return null;
    }
}
