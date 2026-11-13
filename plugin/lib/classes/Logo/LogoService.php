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
 *
 * Instead of an address, root can upload a picture. It passes the same
 * checks and takes precedence: while it is there, the address is neither
 * fetched nor served, and removing the upload brings the address back.
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
        $previous = $this->store->load();
        if ($previous !== null && $previous->isUpload()) {
            return $previous;
        }
        $url = $url !== null ? trim($url) : '';
        if ($url === '' || !LogoFetch::isAcceptableUrl($url)) {
            $this->store->delete();
            return null;
        }
        $now = $this->now();
        try {
            $logo = new StoredLogo($url, $this->fetch->fetch($url), $now, $now, null);
        } catch (LogoRejected $rejected) {
            $logo = $previous !== null && $previous->sourceUrl === $url && $previous->image !== null
                ? new StoredLogo($url, $previous->image, $previous->fetchedAt, $now, $rejected->reason)
                : new StoredLogo($url, null, null, $now, $rejected->reason);
        }
        $this->store->save($logo);
        return $logo;
    }

    /**
     * Stores an uploaded picture in place of the address. A picture the
     * checks refuse changes nothing.
     *
     * @throws LogoRejected
     */
    public function upload(string $bytes): StoredLogo
    {
        $now = $this->now();
        $logo = new StoredLogo(StoredLogo::UPLOAD, LogoImage::fromBytes($bytes), $now, $now, null);
        $this->store->save($logo);
        return $logo;
    }

    /**
     * Deletes an uploaded picture and goes back to the configured address,
     * which is fetched at once. Null when no address is configured.
     */
    public function removeUpload(?string $url): ?StoredLogo
    {
        if ($this->store->load()?->isUpload() === true) {
            $this->store->delete();
        }
        return $this->refresh($url);
    }

    /**
     * The uploaded picture, or the state for the configured address with or
     * without a copy; null when there is neither an upload nor anything
     * tried for the configured address.
     */
    public function status(?string $url): ?StoredLogo
    {
        $logo = $this->store->load();
        if ($logo !== null && $logo->isUpload()) {
            return $logo;
        }
        return $logo !== null && $url !== null && $logo->sourceUrl === trim($url) ? $logo : null;
    }

    /** The picture to serve: the upload, else the copy for the configured address. */
    public function current(?string $url): ?LogoImage
    {
        return $this->status($url)?->image;
    }

    private function now(): int
    {
        return $this->clock !== null ? ($this->clock)() : time();
    }
}
