<?php

declare(strict_types=1);

namespace Tests\Database;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Ract\Config\Config;
use Ract\Database\Connection;
use Ract\Database\DatabaseManager;
use Ract\Database\Schema\Blueprint;
use Ract\Database\Schema\SchemaBuilder;

final class DatabaseTest extends TestCase
{
    private DatabaseManager $database;

    private SchemaBuilder $schema;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The pdo_sqlite extension is required for database tests.');
        }

        $this->database = new DatabaseManager(new Config([
            'database' => [
                'default' => 'sqlite',
                'connections' => [
                    'sqlite' => [
                        'driver' => 'sqlite',
                        'database' => ':memory:',
                    ],
                ],
            ],
        ]));
        $this->schema = new SchemaBuilder($this->database->connection());
        $this->schema->create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('published')->default(false);
            $table->timestamps();
        });
    }

    public function testQueryBuilderCreatesReadsUpdatesAndDeletesRows(): void
    {
        $posts = $this->database->table('posts');
        $id = $posts->insertGetId([
            'title' => 'First post',
            'published' => true,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        self::assertSame('First post', $this->database->table('posts')->find($id)['title']);
        self::assertSame(1, $this->database->table('posts')->where('published', true)->count());
        self::assertSame(1, $this->database->table('posts')->where('id', $id)->update(['title' => 'Updated']));
        self::assertSame('Updated', $this->database->table('posts')->where('id', $id)->value('title'));
        self::assertSame(1, $this->database->table('posts')->where('id', $id)->delete());
        self::assertSame(0, $this->database->table('posts')->count());
    }

    public function testValueReadsQualifiedColumns(): void
    {
        $this->database->table('posts')->insert([
            'title' => 'Qualified title',
            'published' => false,
        ]);

        self::assertSame(
            'Qualified title',
            $this->database->table('posts')->value('posts.title'),
        );
    }

    public function testBindingsPreserveBooleanTypes(): void
    {
        $this->database->table('posts')->insert([
            'title' => 'Draft',
            'published' => false,
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-01 00:00:00',
        ]);

        self::assertSame(
            'integer',
            $this->database->connection()->scalar('SELECT typeof(published) FROM posts'),
        );
    }

    public function testTransactionsRollBackWhenTheCallbackFails(): void
    {
        try {
            $this->database->transaction(function (): void {
                $this->database->table('posts')->insert([
                    'title' => 'Rolled back',
                    'created_at' => '2026-01-01 00:00:00',
                    'updated_at' => '2026-01-01 00:00:00',
                ]);
                throw new \RuntimeException('stop');
            });
            self::fail('The transaction should rethrow the callback exception.');
        } catch (\RuntimeException $exception) {
            self::assertSame('stop', $exception->getMessage());
        }

        self::assertSame(0, $this->database->table('posts')->count());
    }

    public function testNestedTransactionsRollBackInnerWorkWhenTheOuterTransactionContinues(): void
    {
        $this->database->transaction(function (): void {
            $this->database->table('posts')->insert(['title' => 'Outer before']);

            try {
                $this->database->transaction(function (): void {
                    $this->database->table('posts')->insert(['title' => 'Inner']);
                    throw new \RuntimeException('inner failure');
                });
            } catch (\RuntimeException $exception) {
                self::assertSame('inner failure', $exception->getMessage());
            }

            $this->database->table('posts')->insert(['title' => 'Outer after']);
        });

        self::assertSame(
            ['Outer before', 'Outer after'],
            array_column($this->database->table('posts')->orderBy('id')->get(), 'title'),
        );
    }

    public function testItRejectsUnsafeSqlIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->database->table('posts; DROP TABLE posts');
    }

    public function testItExposesTheConfiguredPdoConnection(): void
    {
        self::assertInstanceOf(PDO::class, $this->database->connection()->pdo());
        self::assertSame('sqlite', $this->database->connection()->driver());
    }

    public function testMysqlOffsetQueriesCompileWithoutAnExplicitLimit(): void
    {
        $connection = new class (new PDO('sqlite::memory:')) extends Connection {
            public function driver(): string
            {
                return 'mysql';
            }
        };

        self::assertSame(
            'SELECT * FROM `posts` LIMIT 18446744073709551615 OFFSET 5',
            $connection->table('posts')->offset(5)->toSql(),
        );
    }

    public function testPostgresTableLookupPreservesMixedCaseIdentifiers(): void
    {
        $connection = new class (new PDO('sqlite::memory:')) extends Connection {
            public string $sql = '';

            /** @var list<mixed> */
            public array $bindings = [];

            public function driver(): string
            {
                return 'pgsql';
            }

            public function scalar(string $sql, array $bindings = []): mixed
            {
                $this->sql = $sql;
                $this->bindings = $bindings;

                return 1;
            }
        };
        $schema = new SchemaBuilder($connection);

        self::assertTrue($schema->hasTable('Users'));
        self::assertSame(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?',
            $connection->sql,
        );
        self::assertSame(['Users'], $connection->bindings);
    }

    public function testPostgresDateTimeColumnsCompileAsTimestamps(): void
    {
        $connection = new class (new PDO('sqlite::memory:')) extends Connection {
            public string $sql = '';

            public function driver(): string
            {
                return 'pgsql';
            }

            public function statement(string $sql, array $bindings = []): bool
            {
                $this->sql = $sql;

                return true;
            }
        };
        $schema = new SchemaBuilder($connection);

        $schema->create('events', static function (Blueprint $table): void {
            $table->id();
            $table->dateTime('starts_at');
            $table->boolean('published')->default(false);
        });

        self::assertStringContainsString('"id" BIGSERIAL PRIMARY KEY', $connection->sql);
        self::assertStringContainsString('"starts_at" TIMESTAMP NOT NULL', $connection->sql);
        self::assertStringContainsString('"published" BOOLEAN NOT NULL DEFAULT FALSE', $connection->sql);
    }

    public function testPostgresInsertIdsUseReturning(): void
    {
        $connection = new class (new PDO('sqlite::memory:')) extends Connection {
            public string $sql = '';

            /** @var list<mixed> */
            public array $bindings = [];

            public function driver(): string
            {
                return 'pgsql';
            }

            public function scalar(string $sql, array $bindings = []): mixed
            {
                $this->sql = $sql;
                $this->bindings = $bindings;

                return '42';
            }
        };

        $id = $connection->table('posts')->insertGetId(['title' => 'Postgres'], 'post_id');

        self::assertSame(42, $id);
        self::assertSame(
            'INSERT INTO "posts" ("title") VALUES (?) RETURNING "post_id"',
            $connection->sql,
        );
        self::assertSame(['Postgres'], $connection->bindings);
    }

    public function testIncrementsUseTheDriverAppropriateIntegerWidth(): void
    {
        foreach (['mysql' => '`id` INT AUTO_INCREMENT PRIMARY KEY', 'pgsql' => '"id" SERIAL PRIMARY KEY'] as $driver => $expected) {
            $connection = new class (new PDO('sqlite::memory:'), $driver) extends Connection {
                public string $sql = '';

                public function __construct(PDO $pdo, private readonly string $driverName)
                {
                    parent::__construct($pdo);
                }

                public function driver(): string
                {
                    return $this->driverName;
                }

                public function statement(string $sql, array $bindings = []): bool
                {
                    $this->sql = $sql;

                    return true;
                }
            };
            $schema = new SchemaBuilder($connection);

            $schema->create('counters', static function (Blueprint $table): void {
                $table->increments();
            });

            self::assertStringContainsString($expected, $connection->sql);
        }
    }
}
