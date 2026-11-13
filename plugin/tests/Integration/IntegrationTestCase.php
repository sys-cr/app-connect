<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests against a real database (`make test-integration`). The plugin's
 * tables come from its own migrations; the core's OAuth2 tables are
 * created with the columns the plugin reads. Without APP_CONNECT_TEST_DB
 * the tests are skipped.
 */
abstract class IntegrationTestCase extends TestCase
{
    private const MIGRATIONS = [
        '002_create_devices_and_pairing_codes.php',
        '009_add_web_login.php',
        '017_count_limits_in_the_database.php',
    ];

    public static function setUpBeforeClass(): void
    {
        if ((string) getenv('APP_CONNECT_TEST_DB') === '') {
            self::markTestSkipped('APP_CONNECT_TEST_DB is not set; run make test-integration');
        }
        require_once __DIR__ . '/../Support/IntegrationDatabase.php';
    }

    protected function setUp(): void
    {
        \DBManager::reconnect();
        $db = \DBManager::get();
        foreach (['app_connect_devices', 'app_connect_pairing_codes', 'app_connect_web_login_tickets',
            'app_connect_counters', 'oauth2_refresh_tokens', 'oauth2_access_tokens'] as $table) {
            $db->exec("DROP TABLE IF EXISTS `$table`");
        }
        foreach (self::MIGRATIONS as $file) {
            $migration = require __DIR__ . '/../../migrations/' . $file;
            $migration->migrateUp();
        }
        // The columns of the core tables (Stud.IP 6.0 to 6.3) the plugin uses.
        $db->exec('CREATE TABLE `oauth2_access_tokens` (
            `id` VARCHAR(100) NOT NULL PRIMARY KEY, `user_id` CHAR(32) NULL, `client_id` INT NOT NULL,
            `scopes` TEXT NULL, `revoked` TINYINT(1) NOT NULL DEFAULT 0, `expires_at` INT NULL,
            `mkdate` INT NOT NULL DEFAULT 0, `chdate` INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
        $db->exec('CREATE TABLE `oauth2_refresh_tokens` (
            `id` VARCHAR(100) NOT NULL PRIMARY KEY, `access_token_id` VARCHAR(100) NOT NULL,
            `revoked` TINYINT(1) NOT NULL DEFAULT 0, `expires_at` INT NULL,
            `mkdate` INT NOT NULL DEFAULT 0, `chdate` INT NOT NULL DEFAULT 0) ENGINE=InnoDB');
    }

    /**
     * Runs $work in $count processes at once and returns their results in
     * order. Each process gets its index and its own database connection.
     *
     * @param \Closure(int): (string|null) $work
     * @return list<string|null>
     */
    protected function concurrently(int $count, \Closure $work): array
    {
        if (!function_exists('pcntl_fork')) {
            self::markTestSkipped('pcntl is needed for concurrent requests');
        }
        $files = [];
        $pids = [];
        for ($i = 0; $i < $count; $i++) {
            $files[$i] = tempnam(sys_get_temp_dir(), 'app-connect-it');
            $pid = pcntl_fork();
            if ($pid === 0) {
                \DBManager::reconnect();
                file_put_contents($files[$i], serialize($work($i)));
                exit(0);
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        \DBManager::reconnect();
        $results = [];
        foreach ($files as $file) {
            $results[] = unserialize((string) file_get_contents($file));
            unlink($file);
        }
        return $results;
    }
}
