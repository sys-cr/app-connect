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
     * Answers like an OAuth2 error and ends the request when {@see refuses()}
     * holds. The client id is looked up only for a refresh at the core path.
     *
     * @param \Closure(): ?string $appClientId
     */
    public static function apply(\Closure $appClientId): void
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        if (($_POST['grant_type'] ?? null) !== 'refresh_token'
            || !str_ends_with(rtrim($path, '/'), self::CORE_TOKEN_PATH)) {
            return;
        }
        $refuses = self::refuses(
            (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            $path,
            'refresh_token',
            [
                is_string($_POST['client_id'] ?? null) ? $_POST['client_id'] : null,
                is_string($_SERVER['PHP_AUTH_USER'] ?? null) ? $_SERVER['PHP_AUTH_USER'] : null,
            ],
            $appClientId()
        );
        if (!$refuses) {
            return;
        }
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
