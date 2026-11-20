<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * {@see HttpFetcher} with the curl extension, which Stud.IP requires: https
 * only, five seconds in all, no redirects, no cookies, certificate checked.
 * The host is pinned to the address {@see LogoFetch} checked, so a second
 * DNS answer cannot lead the request elsewhere: CURLOPT_RESOLVE for a direct
 * connection, CURLOPT_CONNECT_TO for a proxy (the core's HTTP_PROXY), which
 * then opens its tunnel to that address instead of resolving the name
 * itself. The certificate is still checked against the host name.
 */
final class CurlHttpFetcher implements HttpFetcher
{
    public const TIMEOUT_SECONDS = 5;

    /** @param string|null $proxy host:port of the core's HTTP_PROXY, null for a direct connection */
    public function __construct(private readonly ?string $proxy = null)
    {
    }

    public function get(string $url, string $address, int $maxBytes): HttpReply
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $port = (int) ($parts['port'] ?? 443);
        $pinned = str_contains($address, ':') ? '[' . $address . ']' : $address;

        $body = '';
        $truncated = false;
        $location = null;
        $handle = curl_init($url);
        if ($handle === false) {
            throw new LogoRejected(LogoRejected::UNREACHABLE);
        }
        curl_setopt_array($handle, [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPGET => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $pinned],
            CURLOPT_CONNECT_TO => [$host . ':' . $port . ':' . $pinned . ':' . $port],
            CURLOPT_USERAGENT => 'app-connect (Stud.IP plugin)',
            CURLOPT_HTTPHEADER => ['Accept: image/png, image/jpeg, image/webp'],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$location): int {
                if (stripos($line, 'Location:') === 0) {
                    $location = trim(substr($line, strlen('Location:')));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$truncated, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $truncated = true;
                    return 0; // Ends the transfer.
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($this->proxy !== null && $this->proxy !== '') {
            curl_setopt($handle, CURLOPT_PROXY, $this->proxy);
        }
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if ($truncated) {
            return new HttpReply($status, '', null, true);
        }
        if ($ok === false || $status === 0) {
            throw new LogoRejected(LogoRejected::UNREACHABLE);
        }
        return new HttpReply($status, $body, $location);
    }
}
