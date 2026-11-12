<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Support;

use AppConnect\Logo\HttpFetcher;
use AppConnect\Logo\HttpReply;
use AppConnect\Logo\LogoRejected;

/**
 * {@see HttpFetcher} without network: answers from a table of URLs and
 * records every request with the address it was pinned to.
 */
final class FakeHttpFetcher implements HttpFetcher
{
    /** @var list<array{url: string, address: string}> */
    public array $requests = [];

    /** @param array<string, HttpReply> $replies URL → answer; a missing URL is unreachable */
    public function __construct(private array $replies = [])
    {
    }

    public function answer(string $url, HttpReply $reply): void
    {
        $this->replies[$url] = $reply;
    }

    public function get(string $url, string $address, int $maxBytes): HttpReply
    {
        $this->requests[] = ['url' => $url, 'address' => $address];
        $reply = $this->replies[$url] ?? null;
        if ($reply === null) {
            throw new LogoRejected(LogoRejected::UNREACHABLE);
        }
        if (strlen($reply->body) > $maxBytes) {
            return new HttpReply($reply->status, '', null, true);
        }
        return $reply;
    }

    public static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/logo/' . $name);
    }
}
