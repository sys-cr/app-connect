<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Discovery;

/**
 * The discovery payload in the core's cache for a minute, per base URL, so
 * a request whose ETag matches costs no settings, manifest and logo reads.
 * A change in the section "Stud.App" or of the logo starts a new
 * generation, which drops every cached payload at once.
 */
final class DiscoveryCache
{
    public const SECONDS = 60;

    private const PREFIX = 'app-connect/discovery/';

    /**
     * @param \Closure(): array<string, mixed> $build
     */
    public static function response(string $baseUrl, \Closure $build): DiscoveryResponse
    {
        $cache = \Studip\Cache\Factory::getCache();
        $key = self::PREFIX . self::generation($cache) . '/' . hash('sha256', $baseUrl);
        $payload = $cache->read($key);
        if (!is_array($payload)) {
            $payload = $build();
            $cache->write($key, $payload, self::SECONDS);
        }
        return new DiscoveryResponse($payload);
    }

    /** After a change that shows in the discovery. */
    public static function forget(): void
    {
        $cache = \Studip\Cache\Factory::getCache();
        $cache->write(self::PREFIX . 'generation', bin2hex(random_bytes(8)), 86400);
    }

    private static function generation(\Studip\Cache\Cache $cache): string
    {
        $generation = $cache->read(self::PREFIX . 'generation');
        return is_string($generation) && $generation !== '' ? $generation : '0';
    }

    private function __construct()
    {
    }
}
