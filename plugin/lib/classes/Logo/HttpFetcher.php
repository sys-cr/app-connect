<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * One GET request without following redirects. {@see LogoFetch} checks
 * every address and every redirect itself; the fetcher only talks to the
 * server.
 */
interface HttpFetcher
{
    /**
     * @param string $url      https address of the request
     * @param string $address  IP address the host was resolved to and checked
     *                         against; the connection goes there and nowhere else
     * @param int    $maxBytes reading stops after this many bytes of the body
     *
     * @throws LogoRejected with {@see LogoRejected::UNREACHABLE} when no answer arrives
     */
    public function get(string $url, string $address, int $maxBytes): HttpReply;
}
