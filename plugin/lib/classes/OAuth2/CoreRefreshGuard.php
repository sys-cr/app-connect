<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\OAuth2;

/**
 * Keeps the app client's refresh tokens away from the core's token endpoint.
 *
 * A device is bound to the lineage of its tokens, and only the plugin's
 * token endpoint moves that binding along. A refresh at the core endpoint
 * would break the lineage, so removing the device could miss the new
 * tokens. The apps never refresh there (the discovery names the plugin's
 * endpoint); whoever does holds a refresh token outside the app.
 *
 * The plugin is a system plugin: the core loads it in its session middleware
 * before the controller of `dispatch.php/api/oauth2/token` runs, so the
 * refusal comes first.
 */
final class CoreRefreshGuard
{
    public const CORE_TOKEN_PATH = '/dispatch.php/api/oauth2/token';

    /**
     * Whether the request is a refresh of the app client at the core endpoint.
     *
     * @param list<string|null> $clientIds the client id from the body and from HTTP Basic
     */
    public static function refuses(
        string $method,
        string $path,
        ?string $grantType,
        array $clientIds,
        ?string $appClientId
    ): bool {
        return $appClientId !== null
            && strtoupper($method) === 'POST'
            && str_ends_with(rtrim($path, '/'), self::CORE_TOKEN_PATH)
            && $grantType === 'refresh_token'
            && in_array($appClientId, $clientIds, true);
    }

    /**
     * Whether the request described by the server and form parameters is a
     * refresh of the app client at the core endpoint. The client counts as
     * named wherever league/oauth2-server would find it: `client_id` in the
     * body, or the user of HTTP Basic, which PHP passes as PHP_AUTH_USER
     * under mod_php and only as the raw header under FastCGI.
     *
     * @param array<mixed> $server
     * @param array<mixed> $post
     * @param \Closure(): ?string $appClientId looked up only for a refresh at the core path
     */
    public static function check(array $server, array $post, \Closure $appClientId): bool
    {
        $path = parse_url(is_string($server['REQUEST_URI'] ?? null) ? $server['REQUEST_URI'] : '', PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        if (($post['grant_type'] ?? null) !== 'refresh_token'
            || !str_ends_with(rtrim($path, '/'), self::CORE_TOKEN_PATH)) {
            return false;
        }
        return self::refuses(
            is_string($server['REQUEST_METHOD'] ?? null) ? $server['REQUEST_METHOD'] : '',
            $path,
            'refresh_token',
            [
                is_string($post['client_id'] ?? null) ? $post['client_id'] : null,
                is_string($server['PHP_AUTH_USER'] ?? null) ? $server['PHP_AUTH_USER'] : null,
                self::basicUser($server['HTTP_AUTHORIZATION'] ?? null),
                self::basicUser($server['REDIRECT_HTTP_AUTHORIZATION'] ?? null),
            ],
            $appClientId()
        );
    }

    /**
     * The user of an `Authorization: Basic` header, split as
     * league/oauth2-server splits it; null for anything else. The scheme is
     * matched case-insensitively, which refuses at least what league accepts.
     */
    public static function basicUser(mixed $header): ?string
    {
        if (!is_string($header) || preg_match('/^Basic\s+(\S+)/i', $header, $match) !== 1) {
            return null;
        }
        $decoded = base64_decode($match[1], true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }
        return explode(':', $decoded, 2)[0];
    }

    /**
     * Answers like an OAuth2 error and ends the request when {@see check()}
     * holds for the current request.
     *
     * @param \Closure(): ?string $appClientId
     */
    public static function apply(\Closure $appClientId): void
    {
        if (self::check($_SERVER, $_POST, $appClientId)) {
            self::respondAndExit();
        }
    }

    private static function respondAndExit(): never
    {
        http_response_code(400);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode([
            'error' => 'invalid_grant',
            'error_description' => 'Tokens of this client are refreshed at the app-connect token endpoint.',
        ]);
        exit;
    }

    private function __construct()
    {
    }
}
