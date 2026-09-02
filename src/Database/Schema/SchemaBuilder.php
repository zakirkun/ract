<?php

declare(strict_types=1);

namespace Ract\Database\Schema;

use InvalidArgumentException;
use Ract\Database\Connection;

final class SchemaBuilder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($this->validateIdentifier($table));
        $callback($blueprint);

        if ($blueprint->columns() === []) {
            throw new InvalidArgumentException('A new table must define at least one column.');
        }

        $columns = array_map(
            fn (ColumnDefinition $column): string => $this->compileColumn($column),
            $blueprint->columns(),
        );
        $sql = sprintf(
            'CREATE TABLE %s (%s)',
            $this->quoteIdentifier($table),
            implode(', ', $columns),
        );
        $this->connection->statement($sql);
    }

    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($this->validateIdentifier($table));
        $callback($blueprint);

        foreach ($blueprint->columns() as $column) {
            if ($column->primary || $column->unique) {
                throw new InvalidArgumentException('Primary and unique columns cannot be added to an existing table.');
            }

            $this->connection->statement(sprintf(
                'ALTER TABLE %s ADD COLUMN %s',
                $this->quoteIdentifier($table),
                $this->compileColumn($column),
            ));
        }
    }

    public function hasTable(string $table): bool
    {
        $this->validateIdentifier($table);

        return match ($this->connection->driver()) {
            'sqlite' => $this->connection->scalar(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table],
            ) > 0,
            'mysql' => $this->connection->scalar(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table],
            ) > 0,
            'pgsql' => $this->connection->scalar(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?',
                [$table],
            ) > 0,
            default => false,
        };
    }

    public function drop(string $table): void
    {
        $this->connection->statement('DROP TABLE ' . $this->quoteIdentifier($this->validateIdentifier($table)));
    }

    public function dropIfExists(string $table): void
    {
        $this->connection->statement('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($this->validateIdentifier($table)));
    }

    private function compileColumn(ColumnDefinition $column): string
    {
        if ($column->autoIncrement) {
            return $this->compileAutoIncrement($column);
        }

        $sql = $this->quoteIdentifier($column->name) . ' ' . $this->columnType($column);
        $sql .= $column->nullable ? ' NULL' : ' NOT NULL';

        if ($column->hasDefault) {
            $sql .= ' DEFAULT ' . $this->quoteDefault($column->defaultValue);
        }

        if ($column->primary) {
            $sql .= ' PRIMARY KEY';
        }

        if ($column->unique) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    private function compileAutoIncrement(ColumnDefinition $column): string
    {
        $name = $this->quoteIdentifier($column->name);

        return match ($this->connection->driver()) {
            'mysql' => $name . ($column->type === 'integer' ? ' INT' : ' BIGINT') . ' AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => $name . ($column->type === 'integer' ? ' SERIAL' : ' BIGSERIAL') . ' PRIMARY KEY',
            default => $name . ' INTEGER PRIMARY KEY AUTOINCREMENT',
        };
    }

    private function columnType(ColumnDefinition $column): string
    {
        return match ($column->type) {
            'string' => sprintf('VARCHAR(%d)', $column->parameters['length']),
            'text' => 'TEXT',
            'integer' => 'INTEGER',
            'big_integer' => 'BIGINT',
            'boolean' => $this->connection->driver() === 'sqlite' ? 'INTEGER' : 'BOOLEAN',
            'decimal' => sprintf(
                'DECIMAL(%d, %d)',
                $column->parameters['precision'],
                $column->parameters['scale'],
            ),
            'datetime' => $this->connection->driver() === 'pgsql' ? 'TIMESTAMP' : 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            default => throw new InvalidArgumentException(sprintf('Unsupported column type "%s".', $column->type)),
        };
    }

    private function quoteDefault(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            if ($this->connection->driver() === 'pgsql') {
                return $value ? 'TRUE' : 'FALSE';
            }

            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $this->connection->pdo()->quote((string) $value);
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->validateIdentifier($identifier);
        $quote = $this->connection->driver() === 'mysql' ? '`' : '"';

        return $quote . $identifier . $quote;
    }

    private function validateIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid schema identifier "%s".', $identifier));
        }

        return $identifier;
    }
}
