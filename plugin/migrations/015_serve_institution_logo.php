<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/**
 * The plugin serves the institution logo itself: the table for its checked
 * copy (one row) and the description of APP_CONNECT_LOGO_URL, which now
 * says so. Config::create() writes a description only once, so it is set
 * here. Going down drops the copy with the table.
 */
return new class extends Migration
{
    /** Field => description that replaces the one an earlier migration created. */
    public const DESCRIPTIONS = [
        'APP_CONNECT_LOGO_URL' => 'Adresse des Hochschul-Logos für die App, beginnend mit https://. Das Bild wird vom Plugin abgerufen und auf diesem Server ausgeliefert; PNG, JPEG oder WebP, höchstens 512 KB, 32 bis 2048 Pixel je Seite.',
    ];

    /** The description before this migration, restored when going down. */
    private const PREVIOUS_DESCRIPTIONS = [
        'APP_CONNECT_LOGO_URL' => 'Adresse des Hochschul-Logos für die App, beginnend mit https://.',
    ];

    public function description()
    {
        return 'Adds the table for the copy of the institution logo that the plugin serves.';
    }

    protected function up()
    {
        $db = DBManager::get();
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `app_connect_logo` (
                `id` TINYINT UNSIGNED NOT NULL,
                `source_url` VARCHAR(2048) NOT NULL,
                `content` MEDIUMBLOB NULL,
                `mime_type` VARCHAR(16) CHARACTER SET latin1 COLLATE latin1_bin NULL,
                `content_hash` CHAR(64) CHARACTER SET latin1 COLLATE latin1_bin NULL,
                `fetched_at` INT UNSIGNED NULL,
                `checked_at` INT UNSIGNED NOT NULL,
                `problem` VARCHAR(32) CHARACTER SET latin1 COLLATE latin1_bin NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $statement = $db->prepare('UPDATE `config` SET `description` = ? WHERE `field` = ?');
        foreach (self::DESCRIPTIONS as $field => $description) {
            $statement->execute([$description, $field]);
        }
    }

    protected function down()
    {
        $db = DBManager::get();
        $db->exec('DROP TABLE IF EXISTS `app_connect_logo`');
        $statement = $db->prepare('UPDATE `config` SET `description` = ? WHERE `field` = ?');
        foreach (self::PREVIOUS_DESCRIPTIONS as $field => $description) {
            $statement->execute([$description, $field]);
        }
    }
};
