<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

namespace AppConnect\Tests\Unit;

use AppConnect\Audit\EventLog;
use AppConnect\OAuth2\ClientProvisioner;
use AppConnect\Settings\OperatorConfig;
use PHPUnit\Framework\TestCase;

/**
 * The migrations carry the values they create instead of reading them from
 * the classes. Together they must create exactly what the code expects: every
 * configuration entry of the section "Stud.App" and every event log action,
 * each once and with the same type, default and description (the last one a
 * migration wrote).
 */
final class MigrationsTest extends TestCase
{
    private const DIR = __DIR__ . '/../../migrations';

    public static function setUpBeforeClass(): void
    {
        if (!class_exists('Migration', false)) {
            eval('abstract class Migration {}');
        }
    }

    public function testConfigurationEntriesMatchTheDefinitions(): void
    {
        $created = [];
        foreach ($this->migrations() as $name => $migration) {
            $fields = defined(get_class($migration) . '::FIELDS') ? $migration::FIELDS : [];
            foreach ($fields as $field => $definition) {
                self::assertArrayNotHasKey($field, $created, "$field is created twice ($name)");
                $created[$field] = $definition;
            }
            // A later migration may replace the description of an entry created before.
            $descriptions = defined(get_class($migration) . '::DESCRIPTIONS') ? $migration::DESCRIPTIONS : [];
            foreach ($descriptions as $field => $description) {
                self::assertArrayHasKey($field, $created, "$field is described before it is created ($name)");
                $created[$field]['description'] = $description;
            }
        }

        $expected = [];
        foreach (OperatorConfig::definitions() as $field => $definition) {
            $expected[$field] = [
                'type' => $definition['type'],
                'value' => $definition['type'] === 'boolean' ? (int) $definition['value'] : $definition['value'],
                'description' => $definition['description'],
            ];
        }
        ksort($created);
        ksort($expected);
        self::assertSame($expected, $created);
    }

    public function testEventLogActionsMatchTheCode(): void
    {
        $registered = [];
        foreach (['001', '009', '011'] as $prefix) {
            foreach ($this->migration($prefix)::ACTIONS as $name => $action) {
                self::assertArrayNotHasKey($name, $registered);
                $registered[$name] = $action;
            }
        }
        $expected = EventLog::ACTIONS;
        ksort($registered);
        ksort($expected);
        self::assertSame($expected, $registered);
    }

    public function testPersonalActionsExpire(): void
    {
        self::assertSame(EventLog::WEB_LOGIN_RETENTION_SECONDS, $this->migration('010')::RETENTION_SECONDS);
        self::assertSame(EventLog::WEB_LOGIN_ACTIONS, $this->migration('010')::ACTIONS);
        self::assertSame(EventLog::WEB_LOGIN_RETENTION_SECONDS, $this->migration('011')::RETENTION_SECONDS);
        self::assertSame(EventLog::ICAL_KEY_ACTIONS, array_keys($this->migration('011')::ACTIONS));
    }

    public function testTheClientLinksToTheSameWebsite(): void
    {
        self::assertSame(ClientProvisioner::CLIENT_HOMEPAGE, $this->migration('013')::HOMEPAGE);
        self::assertSame(ClientProvisioner::CLIENT_DESCRIPTION, $this->migration('013')::DESCRIPTION);
    }

    /** @return array<string, object> */
    private function migrations(): array
    {
        $migrations = [];
        foreach (glob(self::DIR . '/*.php') as $file) {
            $migrations[basename($file)] = require $file;
        }
        return $migrations;
    }

    private function migration(string $prefix): object
    {
        $files = glob(self::DIR . '/' . $prefix . '_*.php');
        self::assertCount(1, $files);
        return require $files[0];
    }
}
