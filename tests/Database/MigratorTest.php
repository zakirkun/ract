<?php

declare(strict_types=1);

namespace Tests\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use Ract\Config\Config;
use Ract\Database\Connection;
use Ract\Database\DatabaseManager;
use Ract\Database\Migrations\Migrator;
use Ract\Database\Schema\SchemaBuilder;

final class MigratorTest extends TestCase
{
    private SchemaBuilder $schema;

    private Migrator $migrator;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for migration tests.');
        }

        $database = new DatabaseManager(new Config([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => ['driver' => 'sqlite', 'database' => ':memory:'],
                ],
            ],
        ]));
        $connection = $database->connection();
        $this->schema = new SchemaBuilder($connection);
        $this->migrator = new Migrator(
            $connection,
            $this->schema,
            dirname(__DIR__) . '/Fixtures/migrations',
        );
    }

    public function testItRunsEachMigrationOnceAndRollsBackTheLatestBatch(): void
    {
        $name = '2026_01_01_000000_create_notes_table';

        self::assertSame([$name], $this->migrator->migrate());
        self::assertTrue($this->schema->hasTable('notes'));
        self::assertSame([], $this->migrator->migrate());
        self::assertSame([$name], $this->migrator->rollback());
        self::assertFalse($this->schema->hasTable('notes'));
    }

    public function testMysqlMigrationsHandleImplicitDdlCommits(): void
    {
        $connection = new class (new PDO('sqlite::memory:')) extends Connection {
            public function driver(): string
            {
                return 'mysql';
            }

            public function scalar(string $sql, array $bindings = []): mixed
            {
                if (str_contains($sql, 'information_schema.tables')) {
                    return parent::scalar(
                        "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
                        $bindings,
                    );
                }

                return parent::scalar($sql, $bindings);
            }

            public function statement(string $sql, array $bindings = []): bool
            {
                if (preg_match('/^(CREATE|ALTER|DROP) TABLE/i', $sql) === 1 && $this->pdo()->inTransaction()) {
                    $this->pdo()->commit();
                }

                $sql = preg_replace(
                    '/`([^`]+)` (?:BIGINT|INT) AUTO_INCREMENT PRIMARY KEY/',
                    '`$1` INTEGER PRIMARY KEY AUTOINCREMENT',
                    $sql,
                ) ?? $sql;

                return parent::statement($sql, $bindings);
            }
        };
        $schema = new SchemaBuilder($connection);
        $migrator = new Migrator(
            $connection,
            $schema,
            dirname(__DIR__) . '/Fixtures/migrations',
        );
        $name = '2026_01_01_000000_create_notes_table';

        self::assertSame([$name], $migrator->migrate());
        self::assertTrue($schema->hasTable('notes'));
        self::assertSame([$name], $migrator->rollback());
        self::assertFalse($schema->hasTable('notes'));
    }
}
