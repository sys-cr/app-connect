<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Logo\CurlHttpFetcher;
use AppConnect\Logo\LogoRejected;
use PHPUnit\Framework\TestCase;

/**
 * With a proxy the fetch tunnels to the address LogoFetch checked, not to
 * the host name the proxy would resolve itself (#26). A small proxy in a
 * second process records the CONNECT request and refuses it.
 */
final class CurlHttpFetcherTest extends TestCase
{
    public function testTheProxyTunnelGoesToTheCheckedAddress(): void
    {
        self::assertSame('CONNECT 192.0.2.10:443 HTTP/1.1', $this->connectLineFor('192.0.2.10'));
    }

    public function testAnIpv6AddressIsPinnedInBrackets(): void
    {
        self::assertSame('CONNECT [2001:db8::10]:443 HTTP/1.1', $this->connectLineFor('2001:db8::10'));
    }

    private function connectLineFor(string $address): string
    {
        $server = <<<'PHP'
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            fwrite(STDOUT, stream_socket_get_name($socket, false) . "\n");
            $client = stream_socket_accept($socket, 10);
            fwrite(STDOUT, trim((string) fgets($client)) . "\n");
            fwrite($client, "HTTP/1.1 403 Forbidden\r\nContent-Length: 0\r\n\r\n");
            fclose($client);
            PHP;
        $process = proc_open([PHP_BINARY, '-r', $server], [1 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $proxy = trim((string) fgets($pipes[1]));

        try {
            (new CurlHttpFetcher($proxy))->get('https://logo.example.org/logo.png', $address, 1024);
            self::fail('the proxy refuses the tunnel');
        } catch (LogoRejected $refused) {
            self::assertSame(LogoRejected::UNREACHABLE, $refused->reason);
        }
        $line = trim((string) fgets($pipes[1]));
        proc_close($process);
        return $line;
    }
}
