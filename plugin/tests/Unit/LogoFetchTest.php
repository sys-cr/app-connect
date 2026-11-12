<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Logo\AddressGuard;
use AppConnect\Logo\HttpReply;
use AppConnect\Logo\LogoFetch;
use AppConnect\Logo\LogoRejected;
use AppConnect\Tests\Support\FakeHttpFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogoFetchTest extends TestCase
{
    private const INSTANCE = 'studip.example.org';

    private FakeHttpFetcher $http;

    /** @var array<string, list<string>> */
    private array $dns = [
        'studip.example.org' => ['10.0.0.5'],
        'www.example.org' => ['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c'],
        'cdn.example.net' => ['151.101.1.1'],
        'intranet.example.org' => ['192.168.1.10'],
        'split.example.org' => ['93.184.215.15', '127.0.0.1'],
        'metadata.example.org' => ['169.254.169.254'],
        'v6local.example.org' => ['fd00::1'],
    ];

    protected function setUp(): void
    {
        $this->http = new FakeHttpFetcher();
    }

    private function fetcher(): LogoFetch
    {
        return new LogoFetch($this->http, fn (string $host): array => $this->dns[$host] ?? [], self::INSTANCE);
    }

    private function png(): HttpReply
    {
        return new HttpReply(200, FakeHttpFetcher::fixture('logo.png'));
    }

    private function assertRejected(string $reason, string $url): void
    {
        try {
            $this->fetcher()->fetch($url);
            self::fail('fetched ' . $url);
        } catch (LogoRejected $rejected) {
            self::assertSame($reason, $rejected->reason);
        }
    }

    public function testAPublicPictureIsFetchedOverThePinnedAddress(): void
    {
        $this->http->answer('https://www.example.org/img/logo.png', $this->png());

        $image = $this->fetcher()->fetch('https://www.example.org/img/logo.png');

        self::assertSame('image/png', $image->mimeType);
        self::assertSame(
            [['url' => 'https://www.example.org/img/logo.png', 'address' => '93.184.215.14']],
            $this->http->requests
        );
    }

    /** @return iterable<string, array{string}> */
    public static function unacceptableAddresses(): iterable
    {
        yield 'http' => ['http://www.example.org/logo.png'];
        yield 'credentials' => ['https://user:secret@www.example.org/logo.png'];
        yield 'user only' => ['https://user@www.example.org/logo.png'];
        yield 'no host' => ['https:///logo.png'];
        yield 'ftp' => ['ftp://www.example.org/logo.png'];
        yield 'data' => ['data:image/png;base64,AAAA'];
    }

    #[DataProvider('unacceptableAddresses')]
    public function testOnlyHttpsWithoutCredentialsIsFetched(string $url): void
    {
        $this->assertRejected(LogoRejected::ADDRESS, $url);
        self::assertSame([], $this->http->requests);
    }

    /** @return iterable<string, array{string}> */
    public static function internalTargets(): iterable
    {
        yield 'private network' => ['https://intranet.example.org/logo.png'];
        yield 'one of several addresses is loopback' => ['https://split.example.org/logo.png'];
        yield 'link-local (cloud metadata)' => ['https://metadata.example.org/logo.png'];
        yield 'IPv6 unique local' => ['https://v6local.example.org/logo.png'];
        yield 'IPv4 literal' => ['https://127.0.0.1/logo.png'];
        yield 'IPv6 literal' => ['https://[::1]/logo.png'];
    }

    #[DataProvider('internalTargets')]
    public function testInternalAddressesAreRefused(string $url): void
    {
        $this->assertRejected(LogoRejected::INTERNAL, $url);
        self::assertSame([], $this->http->requests);
    }

    public function testTheInstanceHostMayResolveToAPrivateAddress(): void
    {
        $this->http->answer('https://STUDIP.example.org/assets/logo.png', $this->png());

        $this->fetcher()->fetch('https://STUDIP.example.org/assets/logo.png');

        self::assertSame('10.0.0.5', $this->http->requests[0]['address']);
    }

    public function testAnUnknownHostIsUnreachable(): void
    {
        $this->assertRejected(LogoRejected::UNREACHABLE, 'https://nowhere.example.org/logo.png');
    }

    public function testHttpErrorsAreUnreachable(): void
    {
        $this->http->answer('https://www.example.org/logo.png', new HttpReply(404, 'not found'));

        $this->assertRejected(LogoRejected::UNREACHABLE, 'https://www.example.org/logo.png');
    }

    public function testATooLargeAnswerIsCutOffAndRefused(): void
    {
        $this->http->answer('https://www.example.org/logo.png', new HttpReply(200, str_repeat('x', 600 * 1024)));

        $this->assertRejected(LogoRejected::TOO_LARGE, 'https://www.example.org/logo.png');
    }

    public function testRedirectsAreFollowedAndEachTargetIsChecked(): void
    {
        $this->http->answer('https://www.example.org/logo', new HttpReply(301, '', '/img/logo-v2'));
        $this->http->answer('https://www.example.org/img/logo-v2', new HttpReply(302, '', 'https://cdn.example.net/a/logo.png'));
        $this->http->answer('https://cdn.example.net/a/logo.png', new HttpReply(307, '', 'b.png'));
        $this->http->answer('https://cdn.example.net/a/b.png', $this->png());

        $this->fetcher()->fetch('https://www.example.org/logo');

        self::assertSame(
            ['93.184.215.14', '93.184.215.14', '151.101.1.1', '151.101.1.1'],
            array_column($this->http->requests, 'address')
        );
    }

    public function testMoreThanThreeRedirectsAreRefused(): void
    {
        foreach (range(0, 3) as $hop) {
            $this->http->answer("https://www.example.org/$hop", new HttpReply(302, '', '/' . ($hop + 1)));
        }
        $this->http->answer('https://www.example.org/4', $this->png());

        $this->assertRejected(LogoRejected::REDIRECT, 'https://www.example.org/0');
    }

    public function testARedirectToHttpIsRefused(): void
    {
        $this->http->answer('https://www.example.org/logo.png', new HttpReply(301, '', 'http://www.example.org/logo.png'));

        $this->assertRejected(LogoRejected::REDIRECT, 'https://www.example.org/logo.png');
    }

    public function testARedirectIntoTheInternalNetworkIsRefused(): void
    {
        $this->http->answer('https://www.example.org/logo.png', new HttpReply(302, '', 'https://metadata.example.org/latest'));

        $this->assertRejected(LogoRejected::INTERNAL, 'https://www.example.org/logo.png');
        self::assertCount(1, $this->http->requests);
    }

    public function testASvgBehindAnImageAddressIsRefused(): void
    {
        $this->http->answer('https://www.example.org/logo.png', new HttpReply(200, FakeHttpFetcher::fixture('logo.svg')));

        $this->assertRejected(LogoRejected::TYPE, 'https://www.example.org/logo.png');
    }

    /** @return iterable<string, array{string, bool}> */
    public static function addresses(): iterable
    {
        yield ['93.184.215.14', true];
        yield ['2606:2800:21f:cb07:6820:80da:af6b:8b2c', true];
        yield ['127.0.0.1', false];
        yield ['10.1.2.3', false];
        yield ['172.16.0.1', false];
        yield ['172.32.0.1', true];
        yield ['192.168.0.1', false];
        yield ['169.254.169.254', false];
        yield ['100.64.0.1', false];
        yield ['0.0.0.0', false];
        yield ['224.0.0.1', false];
        yield ['::1', false];
        yield ['::', false];
        yield ['::ffff:127.0.0.1', false];
        yield ['fe80::1', false];
        yield ['fc00::1', false];
        yield ['not an address', false];
    }

    #[DataProvider('addresses')]
    public function testTheAddressGuard(string $address, bool $public): void
    {
        self::assertSame($public, AddressGuard::isPublic($address));
    }
}
