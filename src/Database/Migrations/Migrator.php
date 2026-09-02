<?php

declare(strict_types=1);

namespace Ract\Database\Migrations;

use InvalidArgumentException;
use Ract\Database\Connection;
use Ract\Database\Schema\Blueprint;
use Ract\Database\Schema\SchemaBuilder;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SchemaBuilder $schema,
        private readonly string $path,
    ) {
    }

    /** @return list<string> */
    public function migrate(): array
    {
        $this->ensureRepository();
        $ran = array_column($this->connection->table('migrations')->get(['migration']), 'migration');
        $batch = (int) $this->connection->scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') + 1;
        $completed = [];

        foreach ($this->files() as $name => $file) {
            if (in_array($name, $ran, true)) {
                continue;
            }

            $migration = $this->load($file);
            $this->runMigration(function () use ($migration, $name, $batch): void {
                $migration->up($this->schema);
                $this->connection->table('migrations')->insert([
                    'migration' => $name,
                    'batch' => $batch,
                ]);
            });
            $completed[] = $name;
        }

        return $completed;
    }

    /** @return list<string> */
    public function rollback(?int $steps = null): array
    {
        if (!$this->schema->hasTable('migrations')) {
            return [];
        }

        if ($steps !== null && $steps < 1) {
            throw new InvalidArgumentException('Rollback steps must be at least one.');
        }

        $query = $this->connection->table('migrations')->orderBy('id', 'desc');

        if ($steps === null) {
            $batch = $this->connection->scalar('SELECT MAX(batch) FROM migrations');

            if ($batch === null) {
                return [];
            }

            $query->where('batch', (int) $batch);
        } else {
            $query->limit($steps);
        }

        $files = $this->files();
        $rolledBack = [];

        foreach ($query->get() as $record) {
            $name = (string) $record['migration'];
            $file = $files[$name] ?? null;

            if ($file === null) {
                throw new RuntimeException(sprintf('Migration file for "%s" was not found.', $name));
            }

            $migration = $this->load($file);
            $this->runMigration(function () use ($migration, $name): void {
                $migration->down($this->schema);
                $this->connection->table('migrations')->where('migration', $name)->delete();
            });
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    private function runMigration(callable $callback): void
    {
        if ($this->connection->driver() === 'mysql') {
            $callback();

            return;
        }

        $this->connection->transaction($callback);
    }

    private function ensureRepository(): void
    {
        if ($this->schema->hasTable('migrations')) {
            return;
        }

        $this->schema->create('migrations', static function (Blueprint $table): void {
            $table->id();
            $table->string('migration')->unique();
            $table->integer('batch');
        });
    }

    /** @return array<string, string> */
    private function files(): array
    {
        if (!is_dir($this->path)) {
            return [];
        }

        $paths = glob(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($paths, SORT_STRING);
        $files = [];

        foreach ($paths as $path) {
            $files[pathinfo($path, PATHINFO_FILENAME)] = $path;
        }

        return $files;
    }

    private function load(string $file): Migration
    {
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new RuntimeException(sprintf('Migration "%s" must return a Migration instance.', $file));
        }

        return $migration;
    }
}
