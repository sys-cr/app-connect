<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Routes;

use AppConnect\Audit\EventLog;
use AppConnect\Devices\DeviceRecord;
use AppConnect\RateLimit\DatabaseCounterStore;
use AppConnect\RateLimit\Limits;
use AppConnect\RateLimit\RateLimiter;
use AppConnect\Runtime;
use AppConnect\Settings\Settings;
use AppConnect\WebLogin\DatabaseTicketStore;
use AppConnect\WebLogin\ReturnPath;
use AppConnect\WebLogin\WebLoginService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * POST /jsonapi.php/v1/app-connect/web-login – a one-time link that signs
 * the person in on the web (contract/web-login.md). Only the app client may
 * ask for it with its OAuth2 token, or the person with their password (HTTP
 * Basic); other OAuth2 clients and web sessions may not, so a third-party
 * token never turns into a web session.
 */
final class WebLoginCreate extends \JsonApi\NonJsonApiController
{
    public const ENTER_PATH = 'plugins.php/appconnect/web/enter';

    public function __invoke(Request $request, Response $response, $args): Response
    {
        $settings = Runtime::settings();
        if (!$settings->isConnected()) {
            return JsonErrors::write($response, 404, 'app-connect-disabled', 'App connection is switched off');
        }
        if (!$settings->isWebLoginEnabled()) {
            return JsonErrors::write($response, 404, 'app-connect-web-login-disabled', 'Web login is switched off');
        }
        $user = $this->getUser($request);
        if (!$user) {
            return JsonErrors::write($response, 401, 'app-connect-sign-in-required', 'Sign-in required');
        }
        $caller = $this->caller($request, $response, $settings, (string) $user->id, (string) $user->username);
        if ($caller instanceof Response) {
            return $caller;
        }
        [$accessTokenId, $deviceRowId] = $caller;

        $body = self::body($request);
        if ($body === null) {
            return JsonErrors::write($response, 400, 'app-connect-invalid-body', 'The body must be a JSON object');
        }
        $returnTo = null;
        if (isset($body['return_to']) && $body['return_to'] !== '') {
            $returnTo = ReturnPath::parse($body['return_to']);
            if ($returnTo === null) {
                return JsonErrors::write($response, 400, 'app-connect-invalid-return-to', 'return_to must be a path on this Stud.IP');
            }
        }

        $ticket = (new WebLoginService(new DatabaseTicketStore()))
            ->issue((string) $user->id, $returnTo, $accessTokenId, $deviceRowId);
        if ($ticket === null) {
            return JsonErrors::write($response, 429, 'app-connect-too-many-requests', 'Too many web logins, try again later')
                ->withHeader('Retry-After', (string) WebLoginService::WINDOW_SECONDS);
        }
        EventLog::recordWebLogin(EventLog::WEB_LOGIN_ISSUE, (string) $user->id);

        $data = [
            'url' => ReturnPath::absoluteUrl(Runtime::baseUrl(), self::ENTER_PATH) . '#' . $ticket,
            'expiresIn' => WebLoginService::TTL_SECONDS,
        ];
        $response->getBody()->write(json_encode(['data' => $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return $response
            ->withStatus(201)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }

    /**
     * Who is asking: an OAuth2 token of the app client (with its access token
     * and device, if bound) or HTTP Basic for the same person. Anything else
     * is refused.
     *
     * @return array{0: ?string, 1: ?int}|Response
     */
    private function caller(Request $request, Response $response, Settings $settings, string $userId, string $username): array|Response
    {
        $authorization = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s/i', $authorization) === 1) {
            $validated = AppToken::validated($request, $response, (string) $settings->clientId());
            if ($validated instanceof Response) {
                return $validated;
            }
            if ((string) $validated->getAttribute('oauth_user_id') !== $userId) {
                return AppToken::tokenRequired($response);
            }
            $accessTokenId = (string) $validated->getAttribute('oauth_access_token_id');
            $device = DeviceRecord::findOneBySQL(
                '`access_token_id` = ? AND `user_id` = ? AND `revoked_at` IS NULL',
                [$accessTokenId, $userId]
            );
            return [$accessTokenId, $device !== null ? (int) $device->id : null];
        }
        // With HTTP Basic the password is checked here as well: when a web
        // session came first, the core never looked at it. Failed checks are
        // counted per person, so a session alone does not allow guessing the
        // password.
        $server = $request->getServerParams();
        $basicUser = $server['PHP_AUTH_USER'] ?? null;
        $basicPassword = $server['PHP_AUTH_PW'] ?? null;
        if (preg_match('/^Basic\s/i', $authorization) !== 1 || !is_string($basicUser) || !is_string($basicPassword)
            || strcasecmp($basicUser, $username) !== 0) {
            return AppToken::tokenRequired($response);
        }
        $limits = Limits::webLoginPasswords();
        $limiter = new RateLimiter(new DatabaseCounterStore(), $limits, $settings->rateLimitKey());
        if ($limiter->isBlocked($userId)) {
            return JsonErrors::write($response, 429, 'app-connect-too-many-requests', 'Too many failed password checks, try again later')
                ->withHeader('Retry-After', (string) $limits->perAddressWindow);
        }
        if ((\StudipAuthAbstract::CheckAuthentication($basicUser, $basicPassword)['uid'] ?? null) === $userId) {
            return [null, null];
        }
        $limiter->record($userId);
        return AppToken::tokenRequired($response);
    }

    /** @return array<string, mixed>|null the JSON object of the body; [] for an empty body */
    private static function body(Request $request): ?array
    {
        $raw = trim((string) $request->getBody());
        if ($raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return null;
        }
        return is_array($decoded) && !array_is_list($decoded) ? $decoded : ($decoded === [] ? [] : null);
    }
}
