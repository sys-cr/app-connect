<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\AfterResponse;
use AppConnect\Devices\DeviceInput;
use AppConnect\Devices\DeviceRecord;
use AppConnect\Devices\DeviceService;
use AppConnect\Devices\TokenLedger;
use AppConnect\Mail\ConnectedMail;
use AppConnect\Runtime;
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
        if (!$settings->isConnected()) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }

        // The route only works with an OAuth2 token of the app client: the
        // authentication middleware also accepts sessions and basic auth, which
        // have no token lineage to bind.
        $validated = AppToken::validated($request, $response, (string) $settings->clientId());
        if ($validated instanceof Response) {
            return $validated;
        }
        $userId = (string) $validated->getAttribute('oauth_user_id');
        $accessTokenId = (string) $validated->getAttribute('oauth_access_token_id');

        $device = self::device($request->getParsedBody());
        if ($device === null) {
            return JsonErrors::write($response, 422, 'app-connect-invalid-device', 'device-id, device-name and platform are required');
        }

        $row = (new DeviceService(new TokenLedger(), (string) $settings->clientId()))
            ->registerBrowserSignIn($userId, $accessTokenId, $device);
        if ($row === null) {
            return JsonErrors::write($response, 409, 'app-connect-already-registered', 'This sign-in is already registered');
        }
        AfterResponse::run(static fn () => ConnectedMail::send($userId, $row));

        return JsonResponse::write($response, ['data' => self::resource($row)], 201, JsonResponse::JSON_API);
    }

    /** The device of a JSON:API document of type {@see self::TYPE}, null for anything else. */
    private static function device(mixed $body): ?DeviceInput
    {
        $data = is_array($body) ? ($body['data'] ?? null) : null;
        if (!is_array($data) || ($data['type'] ?? null) !== self::TYPE || !is_array($data['attributes'] ?? null)) {
            return null;
        }
        $attributes = $data['attributes'];
        return DeviceInput::fromValues($attributes['device-id'] ?? null, $attributes['device-name'] ?? null, $attributes['platform'] ?? null);
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
