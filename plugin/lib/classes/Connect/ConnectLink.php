<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Connect;

/**
 * Builds and parses connect links and landing page URLs
 * (contract/connect-link.md). The plugin only builds them; the parser keeps the
 * plugin honest against the shared test vectors the apps use.
 */
final class ConnectLink
{
    public const SCHEME = 'de.studapp2.open';
    public const CONNECT_PATH = '/connect';
    public const LANDING_PATH = '/plugins.php/appconnect/get';
    private const LOOPBACK = ['localhost', '127.0.0.1'];

    /** Longest base path in characters (contract/connect-link.md, section 3). */
    public const MAX_BASE_PATH = 200;

    /** One to eight segments of unreserved characters, none of them `.` or `..`. */
    private const BASE_PATH_PATTERN = '~^(?:/(?!\.\.?(?:/|$))[A-Za-z0-9._\~-]{1,63}){1,8}$~';

    /**
     * The installation path of $baseUrl below its host: '' for the root,
     * '/studip' for https://example.org/studip/. Null when the path breaks
     * the rules of the connect link, so the apps could not reach it.
     */
    public static function basePathOf(string $baseUrl): ?string
    {
        $path = parse_url($baseUrl, PHP_URL_PATH);
        $path = rtrim(is_string($path) ? $path : '', '/');
        return $path === '' || self::isBasePath($path) ? $path : null;
    }

    public static function isBasePath(string $path): bool
    {
        return strlen($path) <= self::MAX_BASE_PATH && preg_match(self::BASE_PATH_PATTERN, $path) === 1;
    }

    /** URL of the landing page, the code in the fragment. */
    public static function landingUrl(string $baseUrl, ?string $code): string
    {
        return rtrim($baseUrl, '/') . self::LANDING_PATH . ($code !== null ? '#p=' . $code : '');
    }

    /** The link "Open in the app" hands to the operating system. */
    public static function connectLink(string $host, ?string $code, string $basePath = ''): string
    {
        $query = ['host' => $host];
        if ($basePath !== '') {
            $query['path'] = $basePath;
        }
        if ($code !== null) {
            $query['p'] = $code;
        }
        return self::SCHEME . ':' . self::CONNECT_PATH . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Parses a connect link. $debug enables the loopback exception.
     */
    public static function parseConnectLink(string $input, bool $debug): ConnectLinkResult
    {
        $prefix = self::SCHEME . ':';
        if (strncmp($input, $prefix, strlen($prefix)) !== 0) {
            return ConnectLinkResult::reject('scheme');
        }
        $rest = substr($input, strlen($prefix));
        if (str_starts_with($rest, '//')) {
            return ConnectLinkResult::reject('authority');
        }
        [$path, $query] = array_pad(explode('?', explode('#', $rest, 2)[0], 2), 2, '');
        if ($path !== self::CONNECT_PATH) {
            return ConnectLinkResult::reject('path');
        }
        $params = self::queryParams($query);
        $hosts = $params['host'] ?? [];
        if ($hosts === []) {
            return ConnectLinkResult::reject('hostMissing');
        }
        if (count($hosts) > 1) {
            return ConnectLinkResult::reject('hostDuplicate');
        }
        [$host, $port] = self::splitPort($hosts[0]);
        $hostReason = self::hostReason($host, $port, $debug);
        if ($hostReason !== null) {
            return ConnectLinkResult::reject($hostReason);
        }
        $paths = $params['path'] ?? [];
        if (count($paths) > 1) {
            return ConnectLinkResult::reject('basePathDuplicate');
        }
        $basePath = $paths[0] ?? '';
        if ($paths !== [] && !self::isBasePath($basePath)) {
            return ConnectLinkResult::reject('basePath');
        }
        $codes = $params['p'] ?? [];
        if (count($codes) > 1) {
            return ConnectLinkResult::reject('codeDuplicate');
        }
        $code = null;
        if ($codes !== []) {
            $code = PairingCode::normalize($codes[0]);
            if ($code === null) {
                return ConnectLinkResult::reject('code');
            }
        }
        return ConnectLinkResult::accept($host, $port, $code, $basePath);
    }

    /**
     * Parses a landing page URL (the QR content). $debug enables the loopback
     * exception with plain http.
     */
    public static function parseLandingUrl(string $input, bool $debug): ConnectLinkResult
    {
        $parts = parse_url($input);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return ConnectLinkResult::reject('scheme');
        }
        $host = $parts['host'];
        $port = $parts['port'] ?? null;
        $loopback = in_array($host, self::LOOPBACK, true);
        $schemeOk = $parts['scheme'] === 'https' || ($debug && $loopback && $parts['scheme'] === 'http');
        if (!$schemeOk) {
            return ConnectLinkResult::reject('scheme');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return ConnectLinkResult::reject('host');
        }
        $path = $parts['path'] ?? '';
        if (!str_ends_with($path, self::LANDING_PATH)) {
            return ConnectLinkResult::reject('path');
        }
        $basePath = substr($path, 0, -strlen(self::LANDING_PATH));
        if ($basePath !== '' && !self::isBasePath($basePath)) {
            return ConnectLinkResult::reject('basePath');
        }
        if (isset($parts['query'])) {
            return ConnectLinkResult::reject('query');
        }
        $hostReason = self::hostReason($host, $port, $debug);
        if ($hostReason !== null) {
            return ConnectLinkResult::reject($hostReason);
        }
        $code = null;
        if (isset($parts['fragment'])) {
            parse_str($parts['fragment'], $fragment);
            $raw = $fragment['p'] ?? null;
            $code = is_string($raw) ? PairingCode::normalize($raw) : null;
            if ($code === null) {
                return ConnectLinkResult::reject('code');
            }
        }
        return ConnectLinkResult::accept($host, $port, $code, $basePath);
    }

    /** @return array<string, list<string>> */
    private static function queryParams(string $query): array
    {
        $params = [];
        foreach ($query === '' ? [] : explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $params[rawurldecode($name)][] = rawurldecode($value);
        }
        return $params;
    }

    /** @return array{0: string, 1: int|null} */
    private static function splitPort(string $hostValue): array
    {
        if (preg_match('/^([^:\[\]]+):([0-9]{1,5})$/', $hostValue, $m) === 1) {
            return [$m[1], (int) $m[2]];
        }
        return [$hostValue, null];
    }

    private static function hostReason(string $host, ?int $port, bool $debug): ?string
    {
        if (in_array($host, self::LOOPBACK, true)) {
            if (!$debug) {
                return 'loopback';
            }
            return $port === null || ($port >= 1 && $port <= 65535) ? null : 'port';
        }
        if (!self::isDnsName($host)) {
            return 'host';
        }
        return $port === null ? null : 'port';
    }

    private static function isDnsName(string $host): bool
    {
        if (strlen($host) > 253 || preg_match('/^[0-9.]+$/', $host) === 1) {
            return false;
        }
        $labels = explode('.', $host);
        if (count($labels) < 2) {
            return false;
        }
        foreach ($labels as $label) {
            if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }
        return true;
    }
}
