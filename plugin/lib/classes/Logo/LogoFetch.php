<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

use Closure;

/**
 * Fetches the configured logo on the server: only https without
 * credentials, at most three redirects and each of them https, and only
 * from hosts whose every address is public ({@see AddressGuard}). The
 * instance's own host is exempt from the address check, because a Stud.IP
 * behind a firewall may resolve its own name to a private address.
 */
final class LogoFetch
{
    public const MAX_REDIRECTS = 3;

    /**
     * @param Closure(string): list<string> $resolve the IP addresses of a host name
     * @param string $instanceHost host of the Stud.IP installation
     */
    public function __construct(
        private readonly HttpFetcher $http,
        private readonly Closure $resolve,
        private readonly string $instanceHost,
    ) {
    }

    /** @throws LogoRejected */
    public function fetch(string $url): LogoImage
    {
        self::assertHttps($url, LogoRejected::ADDRESS);
        for ($redirects = 0; ; $redirects++) {
            $reply = $this->http->get($url, $this->checkedAddress($url), LogoImage::MAX_BYTES);
            if ($reply->truncated) {
                throw new LogoRejected(LogoRejected::TOO_LARGE);
            }
            if (in_array($reply->status, [301, 302, 303, 307, 308], true)) {
                if ($redirects >= self::MAX_REDIRECTS || $reply->location === null) {
                    throw new LogoRejected(LogoRejected::REDIRECT);
                }
                $url = self::resolveLocation($url, $reply->location);
                self::assertHttps($url, LogoRejected::REDIRECT);
                continue;
            }
            if ($reply->status !== 200) {
                throw new LogoRejected(LogoRejected::UNREACHABLE);
            }
            return LogoImage::fromBytes($reply->body);
        }
    }

    /** https with a host and without user or password. */
    public static function isAcceptableUrl(string $url): bool
    {
        if (preg_match('~^https://[^\s/?#@]+(/\S*)?$~i', $url) !== 1) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && ($parts['host'] ?? '') !== '' && !isset($parts['user']) && !isset($parts['pass']);
    }

    /** @throws LogoRejected */
    private static function assertHttps(string $url, string $reason): void
    {
        if (!self::isAcceptableUrl($url)) {
            throw new LogoRejected($reason);
        }
    }

    /**
     * The address to connect to: every address of the host must be public,
     * so the request cannot be pointed at an internal service by a name that
     * resolves to several addresses.
     *
     * @throws LogoRejected
     */
    private function checkedAddress(string $url): string
    {
        $host = strtolower(trim((string) parse_url($url, PHP_URL_HOST), '[]'));
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolve)($host);
        if ($addresses === []) {
            throw new LogoRejected(LogoRejected::UNREACHABLE);
        }
        if ($host !== strtolower($this->instanceHost)) {
            foreach ($addresses as $address) {
                if (!AddressGuard::isPublic($address)) {
                    throw new LogoRejected(LogoRejected::INTERNAL);
                }
            }
        }
        return $addresses[0];
    }

    /**
     * The absolute address of a Location header relative to $base, resolved
     * as RFC 3986 section 5.2 says: a query alone keeps the path, a relative
     * path is merged with the base, dot segments are removed, a fragment is
     * dropped.
     */
    private static function resolveLocation(string $base, string $location): string
    {
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $location) === 1) {
            return $location;
        }
        if (str_starts_with($location, '//')) {
            return 'https:' . $location;
        }
        $parts = parse_url($base);
        $origin = 'https://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $basePath = (string) ($parts['path'] ?? '/');
        $location = explode('#', $location, 2)[0];
        if ($location === '') {
            return $origin . $basePath . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }
        if (str_starts_with($location, '?')) {
            return $origin . $basePath . $location;
        }
        [$path, $query] = array_pad(explode('?', $location, 2), 2, null);
        if (!str_starts_with($path, '/')) {
            $path = substr($basePath, 0, (int) strrpos($basePath, '/') + 1) . $path;
        }
        return $origin . self::removeDotSegments($path) . ($query !== null ? '?' . $query : '');
    }

    /** RFC 3986 section 5.2.4 for an absolute path; `..` never climbs above the root. */
    private static function removeDotSegments(string $path): string
    {
        $segments = explode('/', $path);
        $last = count($segments) - 1;
        $output = [];
        foreach ($segments as $index => $segment) {
            if ($segment === '.' || $segment === '..') {
                if ($segment === '..' && count($output) > 1) {
                    array_pop($output);
                }
                if ($index === $last) {
                    $output[] = '';
                }
                continue;
            }
            $output[] = $segment;
        }
        $result = implode('/', $output);
        return str_starts_with($result, '/') ? $result : '/' . $result;
    }
}
