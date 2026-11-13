<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

use PHPUnit\Framework\TestCase;

/**
 * Tests against a running Stud.IP with the plugin (`make test-e2e`, set up
 * by tests/e2e/run.sh). They talk HTTP to APP_CONNECT_E2E_URL, the same
 * installation answers below /studip/ as well, and they prepare data with
 * the helper tests/e2e/studip/cli.php inside the container
 * APP_CONNECT_E2E_CONTAINER. Without these variables the tests are skipped.
 */
abstract class E2ETestCase extends TestCase
{
    /** The base path of the second address of the instance. */
    protected const BELOW = '/studip';

    protected const AUTOR = 'e2e_autor';

    protected const ROOT = 'root@studip';

    public const PAIRING_GRANT = 'urn:studip:app-connect:pairing';

    public static function setUpBeforeClass(): void
    {
        if ((string) getenv('APP_CONNECT_E2E_URL') === '' || (string) getenv('APP_CONNECT_E2E_CONTAINER') === '') {
            self::markTestSkipped('APP_CONNECT_E2E_URL and APP_CONNECT_E2E_CONTAINER are not set; run make test-e2e');
        }
        // Every class starts with the app connection on and fresh limits.
        if (!self::helper('state')['enabled']) {
            self::helper('config', 'APP_CONNECT_ENABLED', 'true');
        }
        self::helper('reset-limits');
    }

    /** The address of the instance, optionally below a base path. */
    protected static function url(string $path, string $basePath = ''): string
    {
        return rtrim((string) getenv('APP_CONNECT_E2E_URL'), '/') . $basePath . '/' . ltrim($path, '/');
    }

    /**
     * Runs the helper inside the container as the web server user.
     *
     * @return array<mixed>
     */
    protected static function helper(string ...$args): array
    {
        $command = array_merge(
            ['docker', 'exec', '-u', 'www-data', (string) getenv('APP_CONNECT_E2E_CONTAINER'), 'php', '/opt/app-connect-e2e/cli.php'],
            $args
        );
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        self::assertSame(0, $status, 'helper ' . implode(' ', $args) . ' failed: ' . $stderr . $stdout);
        // Stud.IP may print notices first; the answer is the last line.
        $lines = array_values(array_filter(explode("\n", trim($stdout))));
        $decoded = json_decode((string) end($lines), true);
        self::assertIsArray($decoded, 'helper ' . implode(' ', $args) . ' printed: ' . $stdout);
        return $decoded;
    }

    /**
     * One HTTP request. $body is sent form-encoded (array) or as is (string).
     *
     * @param array<string, string>|string|null $body
     * @param list<string> $headers
     */
    protected static function request(
        string $method,
        string $url,
        array|string|null $body = null,
        array $headers = [],
        ?string $cookieJar = null
    ): Response {
        $curl = curl_init($url);
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
        }
        if ($cookieJar !== null) {
            $options[CURLOPT_COOKIEFILE] = $cookieJar;
            $options[CURLOPT_COOKIEJAR] = $cookieJar;
        }
        curl_setopt_array($curl, $options);
        $raw = curl_exec($curl);
        self::assertIsString($raw, 'request to ' . $url . ' failed: ' . curl_error($curl));
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        if ($cookieJar !== null) {
            // The jar is written when the handle closes (curl_close does nothing since PHP 8.0).
            unset($curl);
        }
        $headerLines = [];
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headerLines[strtolower(trim($name))] = trim($value);
            }
        }
        return new Response($status, $headerLines, substr($raw, $headerSize));
    }

    /** @return array<mixed> */
    protected static function discovery(string $basePath = ''): array
    {
        $response = self::request('GET', self::url('jsonapi.php/v1/app-connect/discovery', $basePath));
        self::assertSame(200, $response->status, $response->body);
        return $response->json();
    }

    protected static function clientId(): string
    {
        return (string) self::discovery()['oauth2']['clientId'];
    }

    /** A new pairing code of $username, as the profile page would make it. */
    protected static function pairingCode(string $username): string
    {
        return (string) self::helper('code', $username)['code'];
    }

    protected static function deviceId(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%s-%s-4%s-8%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 13, 3), substr($hex, 17, 3), substr($hex, 20, 12));
    }

    /** Redeems a pairing code at the plugin's token endpoint. */
    protected static function redeem(string $code, string $deviceId, string $basePath = ''): Response
    {
        return self::request('POST', self::url('jsonapi.php/v1/app-connect/token', $basePath), [
            'grant_type' => self::PAIRING_GRANT,
            'client_id' => self::clientId(),
            'code' => $code,
            'device_id' => $deviceId,
            'device_name' => 'E2E phone',
            'platform' => 'ios',
        ]);
    }

    /**
     * Connects a new device of $username by pairing.
     *
     * @return array{access_token: string, refresh_token: string, device_id: string}
     */
    protected static function pair(string $username, string $basePath = ''): array
    {
        $deviceId = self::deviceId();
        $response = self::redeem(self::pairingCode($username), $deviceId, $basePath);
        self::assertSame(200, $response->status, $response->body);
        $tokens = $response->json();
        return [
            'access_token' => (string) $tokens['access_token'],
            'refresh_token' => (string) $tokens['refresh_token'],
            'device_id' => $deviceId,
        ];
    }

    protected static function refresh(string $refreshToken, string $endpoint = 'jsonapi.php/v1/app-connect/token'): Response
    {
        return self::request('POST', self::url($endpoint), [
            'grant_type' => 'refresh_token',
            'client_id' => self::clientId(),
            'refresh_token' => $refreshToken,
        ]);
    }

    /** A core JSON:API route with the access token. */
    protected static function me(string $accessToken, string $basePath = ''): Response
    {
        return self::request('GET', self::url('jsonapi.php/v1/users/me', $basePath), null, [
            'Authorization: Bearer ' . $accessToken,
        ]);
    }

    /**
     * The device row of $deviceId of $username.
     *
     * @return array<string, mixed>
     */
    protected static function device(string $username, string $deviceId): array
    {
        $rows = array_values(array_filter(
            self::helper('devices', $username),
            static fn (array $row): bool => $row['device_id'] === $deviceId
        ));
        self::assertCount(1, $rows, 'one device row for ' . $deviceId);
        return $rows[0];
    }

    protected static function assertOAuthError(string $error, Response $response): void
    {
        self::assertContains($response->status, [400, 401], $response->body);
        self::assertSame($error, $response->json()['error'] ?? null, $response->body);
    }

    /**
     * A refused refresh. The OAuth2 library of the core answers a revoked or
     * otherwise refused refresh token with `401 invalid_request` ("The
     * refresh token is invalid."), not with `invalid_grant`.
     */
    protected static function assertRefreshRefused(Response $response): void
    {
        self::assertContains($response->status, [400, 401], $response->body);
        self::assertContains($response->json()['error'] ?? null, ['invalid_request', 'invalid_grant'], $response->body);
    }
}
