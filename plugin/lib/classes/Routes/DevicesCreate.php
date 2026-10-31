<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Devices\DeviceInput;
use AppConnect\Devices\DeviceRecord;
use AppConnect\Devices\DeviceService;
use AppConnect\Devices\TokenLedger;
use AppConnect\Mail\ConnectedMail;
use AppConnect\Runtime;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /jsonapi.php/v1/app-connect/devices – registration after a browser
 * sign-in. Binds the lineage of the calling OAuth2 token of the app client to
 * a new device of the signed-in person. 409 when the token is already bound.
 */
final class DevicesCreate extends \JsonApi\NonJsonApiController
{
    public const TYPE = 'app-connect-devices';

    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isEnabled() || $settings->clientId() === null) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }

        // The route only works with an OAuth2 token of the app client: the
        // authentication middleware also accepts sessions and basic auth, which
        // have no token lineage to bind.
        try {
            $validated = (new \Studip\OAuth2\Container())->get(ResourceServer::class)->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $exception) {
            return JsonErrors::write($response, 401, 'app-connect-token-required', 'An OAuth2 token of the app is required');
        }
        if ((string) $validated->getAttribute('oauth_client_id') !== $settings->clientId()) {
            return JsonErrors::write($response, 403, 'app-connect-wrong-client', 'The token does not belong to the app');
        }
        $userId = (string) $validated->getAttribute('oauth_user_id');
        $accessTokenId = (string) $validated->getAttribute('oauth_access_token_id');

        $attributes = ((array) $request->getParsedBody())['data']['attributes'] ?? null;
        $type = ((array) $request->getParsedBody())['data']['type'] ?? null;
        $device = $type === self::TYPE && is_array($attributes)
            ? DeviceInput::fromValues($attributes['device-id'] ?? null, $attributes['device-name'] ?? null, $attributes['platform'] ?? null)
            : null;
        if ($device === null) {
            return JsonErrors::write($response, 422, 'app-connect-invalid-device', 'device-id, device-name and platform are required');
        }

        $row = (new DeviceService(new TokenLedger(), (string) $settings->clientId()))
            ->registerBrowserSignIn($userId, $accessTokenId, $device);
        if ($row === null) {
            return JsonErrors::write($response, 409, 'app-connect-already-registered', 'This sign-in is already registered');
        }
        ConnectedMail::send($userId, $row);

        $response->getBody()->write(json_encode(['data' => self::resource($row)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/vnd.api+json');
    }

    /** @return array<string, mixed> */
    private static function resource(DeviceRecord $row): array
    {
        return [
            'type' => self::TYPE,
            'id' => (string) $row->device_id,
            'attributes' => [
                'device-id' => (string) $row->device_id,
                'device-name' => (string) $row->name,
                'platform' => (string) $row->platform,
                'paired-via' => (string) $row->paired_via,
                'paired-at' => date('c', (int) $row->paired_at),
            ],
        ];
    }
}
