<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\E2E;

/** An HTTP answer of the instance under test. */
final class Response
{
    /** @param array<string, string> $headers lower-case names */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** The value of the CSRF token field of the first form. */
    public function csrfToken(): string
    {
        preg_match('/name="security_token"\s+value="([^"]+)"/', $this->body, $match);
        return html_entity_decode($match[1] ?? '', ENT_QUOTES);
    }
}
