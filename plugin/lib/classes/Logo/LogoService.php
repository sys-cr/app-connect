<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

use Closure;

/**
 * The institution logo as the plugin serves it. The apps load the logo only
 * from the instance's own host, so the plugin fetches the configured
 * address on the server and keeps a checked copy that it delivers itself.
 *
 * A copy belongs to the address it was fetched from: when the address
 * changes, the old copy is no longer served. A failed refresh of the same
 * address keeps the last good copy and records why it failed.
 */
final class LogoService
{
    /** @param Closure(): int|null $clock */
    public function __construct(
        private readonly LogoStore $store,
        private readonly LogoFetch $fetch,
        private readonly ?Closure $clock = null,
    ) {
    }

    /**
     * Fetches $url and stores the result. Without an acceptable address the
     * copy is deleted.
     */
    public function refresh(?string $url): ?StoredLogo
    {
        $url = $url !== null ? trim($url) : '';
        if ($url === '' || !LogoFetch::isAcceptableUrl($url)) {
            $this->store->delete();
            return null;
        }
        $now = $this->now();
        try {
            $logo = new StoredLogo($url, $this->fetch->fetch($url), $now, $now, null);
        } catch (LogoRejected $rejected) {
            $previous = $this->store->load();
            $logo = $previous !== null && $previous->sourceUrl === $url && $previous->image !== null
                ? new StoredLogo($url, $previous->image, $previous->fetchedAt, $now, $rejected->reason)
                : new StoredLogo($url, null, null, $now, $rejected->reason);
        }
        $this->store->save($logo);
        return $logo;
    }

    /**
     * The state for the configured address, with or without a copy; null
     * when no address is configured or nothing was tried for it yet.
     */
    public function status(?string $url): ?StoredLogo
    {
        $logo = $this->store->load();
        return $logo !== null && $url !== null && $logo->sourceUrl === trim($url) ? $logo : null;
    }

    /** The copy to serve for the configured address, if there is one. */
    public function current(?string $url): ?LogoImage
    {
        return $this->status($url)?->image;
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
