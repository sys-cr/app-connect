<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// SPDX-FileCopyrightText: 2026 Bodo Steffen
// Additional terms under GPL-3.0 section 7: see SUPPLEMENTAL-TERMS.txt

declare(strict_types=1);

/*
 * The parts of Stud.IP the integration tests need, for a real MariaDB/MySQL
 * named in APP_CONNECT_TEST_DB (a PDO DSN, user and password in
 * APP_CONNECT_TEST_DB_USER and APP_CONNECT_TEST_DB_PASSWORD): DBManager with
 * the calls the plugin makes, and no-op stand-ins for what the migrations
 * touch besides their tables. Loaded only by the integration tests.
 */

if (!class_exists('DBManager', false)) {
    final class DBManager
    {
        private static ?DBManager $instance = null;

        public function __construct(public readonly PDO $pdo)
        {
        }

        public static function get(): self
        {
            if (self::$instance === null) {
                $pdo = new PDO(
                    (string) getenv('APP_CONNECT_TEST_DB'),
                    (string) getenv('APP_CONNECT_TEST_DB_USER'),
                    (string) getenv('APP_CONNECT_TEST_DB_PASSWORD'),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                self::$instance = new self($pdo);
            }
            return self::$instance;
        }

        /** A fresh connection, e.g. after fork(): a child must not share the parent's. */
        public static function reconnect(): void
        {
            self::$instance = null;
        }

        public function exec(string $sql): int|false
        {
            return $this->pdo->exec($sql);
        }

        /** @param list<mixed> $params */
        public function execute(string $sql, array $params = []): int
        {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            return $statement->rowCount();
        }

        /** @param list<mixed> $params */
        public function fetchColumn(string $sql, array $params = []): mixed
        {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            return $statement->fetchColumn();
        }

        /**
         * @param list<mixed> $params
         * @return array<string, mixed>
         */
        public function fetchOne(string $sql, array $params = []): array
        {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        }

        /**
         * @param list<mixed> $params
         * @return list<mixed>
         */
        public function fetchFirst(string $sql, array $params = []): array
        {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_COLUMN);
        }

        public function beginTransaction(): bool
        {
            return $this->pdo->beginTransaction();
        }

        public function commit(): bool
        {
            return $this->pdo->commit();
        }

        public function rollBack(): bool
        {
            return $this->pdo->rollBack();
        }

        public function inTransaction(): bool
        {
            return $this->pdo->inTransaction();
        }
    }
}

if (!class_exists('Migration', false)) {
    abstract class Migration
    {
        abstract protected function up();

        abstract protected function down();

        public function migrateUp(): void
        {
            $this->up();
        }
    }
}

if (!class_exists('Config', false)) {
    /** Configuration entries are not part of these tests. */
    final class Config
    {
        public static function get(): self
        {
            return new self();
        }

        public function getMetadata(string $field): bool
        {
            return true;
        }

        public function getValue(string $field): mixed
        {
            return null;
        }
    }
}

if (!class_exists('LogAction', false)) {
    /** Event log actions are not part of these tests. */
    final class LogAction
    {
        public ?int $expires = null;

        public static function findOneByName(string $name): self
        {
            return new self();
        }

        public function store(): void
        {
        }
    }
}
