<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Devices\DeviceService;
use AppConnect\Devices\TokenLedger;
use AppConnect\OAuth2\RefreshTokenCipher;
use AppConnect\OAuth2\RevocableToken;
use AppConnect\OAuth2\TokenRevocation;
use AppConnect\RateLimit\Limits;
use AppConnect\RateLimit\RateLimiter;
use AppConnect\RateLimit\StudipCacheCounterStore;
use AppConnect\Runtime;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /jsonapi.php/v1/app-connect/revoke – token revocation (RFC 7009) for
 * the app client, named `oauth2.revocationEndpoint` in the discovery
 * (contract/token-revocation.md). The app calls it when the person signs
 * out: the named token, the other token of its pair and the device bound to
 * the pair end at once. The rules are in {@see TokenRevocation}.
 */
final class TokenRevocationCreate extends \JsonApi\NonJsonApiController
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        $clientId = $settings->clientId();
        if (!$settings->isEnabled() || $clientId === null) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        $limits = Limits::revocations();
        $revocation = new TokenRevocation(
            $clientId,
            new RateLimiter(new StudipCacheCounterStore(), $limits, $settings->rateLimitKey()),
            $limits->perAddressWindow,
            static fn (string $token): ?RevocableToken
                => RefreshTokenCipher::inspector($clientId)?->identify($token),
            static function (RevocableToken $pair) use ($clientId): void {
                $db = \DBManager::get();
                $db->beginTransaction();
                try {
                    (new DeviceService(new TokenLedger(), $clientId))
                        ->revokePair($pair->accessTokenId, $pair->refreshTokenId);
                    $db->commit();
                } catch (\Throwable $exception) {
                    $db->rollBack();
                    throw $exception;
                }
            }
        );
        $result = $revocation->handle(
            (array) $request->getParsedBody(),
            (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '')
        );

        $response = $response
            ->withStatus($result['status'])
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
        if (!isset($result['error'])) {
            return $response;
        }
        $response->getBody()->write(json_encode(
            ['error' => $result['error'], 'error_description' => $result['description'] ?? ''],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        ));
        $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        if (isset($result['retryAfter'])) {
            $response = $response->withHeader('Retry-After', (string) $result['retryAfter']);
        }
        return $response;
    }
}
