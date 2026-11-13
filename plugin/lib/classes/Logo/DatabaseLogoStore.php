<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Logo;

/**
 * {@see LogoStore} in the plugin table `app_connect_logo`, a single row.
 * The database, not a file, holds the copy: it survives clearing the cache,
 * reaches every web server of a cluster and goes with the plugin's other
 * data when the plugin is uninstalled. Loading checks the bytes again, so
 * a row changed by hand is never served unchecked.
 */
final class DatabaseLogoStore implements LogoStore
{
    private const ROW = 1;

    public function load(): ?StoredLogo
    {
        $row = \DBManager::get()->fetchOne(
            'SELECT `source_url`, `content`, `fetched_at`, `checked_at`, `problem`
             FROM `app_connect_logo` WHERE `id` = ?',
            [self::ROW]
        );
        if (!is_array($row) || $row === []) {
            return null;
        }
        $image = null;
        if (is_string($row['content']) && $row['content'] !== '') {
            try {
                $image = LogoImage::fromBytes($row['content']);
            } catch (LogoRejected) {
                $image = null;
            }
        }
        return new StoredLogo(
            (string) $row['source_url'],
            $image,
            $image !== null && $row['fetched_at'] !== null ? (int) $row['fetched_at'] : null,
            (int) $row['checked_at'],
            is_string($row['problem']) && in_array($row['problem'], LogoRejected::REASONS, true) ? $row['problem'] : null,
        );
    }

    public function save(StoredLogo $logo): void
    {
        $statement = \DBManager::get()->prepare(
            'REPLACE INTO `app_connect_logo`
                (`id`, `source_url`, `content`, `mime_type`, `content_hash`, `fetched_at`, `checked_at`, `problem`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $image = $logo->image;
        // Every parameter carries its type: the core's statement would
        // otherwise rebuild the query as text and lose the binary binding.
        $values = [
            [self::ROW, \PDO::PARAM_INT],
            [$logo->sourceUrl, \PDO::PARAM_STR],
            [$image?->bytes, \PDO::PARAM_LOB],
            [$image?->mimeType, \PDO::PARAM_STR],
            [$image?->hash, \PDO::PARAM_STR],
            [$image !== null ? $logo->fetchedAt : null, \PDO::PARAM_INT],
            [$logo->checkedAt, \PDO::PARAM_INT],
            [$logo->problem, \PDO::PARAM_STR],
        ];
        foreach ($values as $index => [$value, $type]) {
            $statement->bindValue($index + 1, $value, $value === null ? \PDO::PARAM_NULL : $type);
        }
        $statement->execute();
    }

    public function delete(): void
    {
        \DBManager::get()->execute('DELETE FROM `app_connect_logo` WHERE `id` = ?', [self::ROW]);
    }
}
