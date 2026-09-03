<?php

declare(strict_types=1);

namespace Ract\Database;

use InvalidArgumentException;
use RuntimeException;

final class QueryBuilder
{
    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array{type: string, column: string, operator?: string, value?: mixed, values?: list<mixed>}> */
    private array $wheres = [];

    /** @var list<array{column: string, direction: string}> */
    private array $orders = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
    ) {
        $this->quoteIdentifier($table);
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function select(string ...$columns): self
    {
        $this->columns = $columns === [] ? ['*'] : $columns;

        foreach ($this->columns as $column) {
            $this->quoteIdentifier($column);
        }

        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        $argumentCount = func_num_args();

        if ($argumentCount === 2) {
            $value = $operator;
            $operator = '=';
        }

        $operator = strtolower((string) $operator);

        if (!in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>=', 'like', 'not like'], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported query operator "%s".', $operator));
        }

        $this->quoteIdentifier($column);

        if ($value === null && in_array($operator, ['=', '!=', '<>'], true)) {
            $this->wheres[] = [
                'type' => $operator === '=' ? 'null' : 'not_null',
                'column' => $column,
            ];

            return $this;
        }

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => strtoupper($operator),
            'value' => $value,
        ];

        return $this;
    }

    public function whereNull(string $column): self
    {
        return $this->where($column, null);
    }

    public function whereNotNull(string $column): self
    {
        return $this->where($column, '!=', null);
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        return $this->addWhereIn($column, $values, false);
    }

    /** @param list<mixed> $values */
    public function whereNotIn(string $column, array $values): self
    {
        return $this->addWhereIn($column, $values, true);
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Query order direction must be "asc" or "desc".');
        }

        $this->quoteIdentifier($column);
        $this->orders[] = ['column' => $column, 'direction' => strtoupper($direction)];

        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Query limit cannot be negative.');
        }

        $this->limitValue = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Query offset cannot be negative.');
        }

        $this->offsetValue = $offset;

        return $this;
    }

    /**
     * @param list<string> $columns
     * @return list<array<string, mixed>>
     */
    public function get(array $columns = ['*']): array
    {
        if ($columns !== ['*']) {
            $this->select(...$columns);
        }

        return $this->connection->select($this->toSql(), $this->getBindings());
    }

    /** @param list<string> $columns @return array<string, mixed>|null */
    public function first(array $columns = ['*']): ?array
    {
        $query = clone $this;
        $rows = $query->limit(1)->get($columns);

        return $rows[0] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function value(string $column): mixed
    {
        $row = $this->first([$column]);
        $separator = strrpos($column, '.');
        $key = $separator === false ? $column : substr($column, $separator + 1);

        return $row[$key] ?? null;
    }

    /** @return list<mixed>|array<int|string, mixed> */
    public function pluck(string $column, ?string $key = null): array
    {
        $columns = $key === null || $key === $column ? [$column] : [$column, $key];
        $rows = $this->get($columns);
        $columnName = $this->unqualifyColumn($column);

        if ($key === null) {
            return array_values(array_map(
                static fn (array $row): mixed => $row[$columnName] ?? null,
                $rows,
            ));
        }

        $keyName = $this->unqualifyColumn($key);
        $values = [];

        foreach ($rows as $row) {
            $values[$row[$keyName]] = $row[$columnName] ?? null;
        }

        return $values;
    }

    public function count(): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s%s',
            $this->quoteIdentifier($this->table),
            $this->compileWheres(),
        );

        return (int) $this->connection->scalar($sql, $this->getBindings());
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /** @param array<string, mixed> $values */
    public function insert(array $values): bool
    {
        [$sql, $bindings] = $this->compileInsert($values);

        return $this->connection->statement($sql, $bindings);
    }

    /** @param array<string, mixed> $values */
    public function insertGetId(array $values, ?string $sequence = null): int|string
    {
        if ($this->connection->driver() === 'pgsql') {
            [$sql, $bindings] = $this->compileInsert($values);
            $id = $this->connection->scalar(
                $sql . ' RETURNING ' . $this->quoteIdentifier($sequence ?? 'id'),
                $bindings,
            );

            if (!is_int($id) && !is_string($id)) {
                throw new RuntimeException('PostgreSQL did not return an inserted ID.');
            }
        } else {
            $this->insert($values);
            $id = $this->connection->lastInsertId($sequence);
        }

        return is_string($id) && ctype_digit($id) ? (int) $id : $id;
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $assignments = [];

        foreach (array_keys($values) as $column) {
            $assignments[] = $this->quoteIdentifier($column) . ' = ?';
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteIdentifier($this->table),
            implode(', ', $assignments),
            $this->compileWheres(),
        );

        return $this->connection->affectingStatement(
            $sql,
            [...array_values($values), ...$this->getBindings()],
        );
    }

    public function delete(): int
    {
        $sql = sprintf(
            'DELETE FROM %s%s',
            $this->quoteIdentifier($this->table),
            $this->compileWheres(),
        );

        return $this->connection->affectingStatement($sql, $this->getBindings());
    }

    public function toSql(): string
    {
        $columns = array_map(fn (string $column): string => $this->quoteIdentifier($column), $this->columns);
        $sql = sprintf(
            'SELECT %s FROM %s%s',
            implode(', ', $columns),
            $this->quoteIdentifier($this->table),
            $this->compileWheres(),
        );

        if ($this->orders !== []) {
            $orders = array_map(
                fn (array $order): string => $this->quoteIdentifier($order['column']) . ' ' . $order['direction'],
                $this->orders,
            );
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        }

        if ($this->offsetValue !== null) {
            if ($this->limitValue === null) {
                $sql .= match ($this->connection->driver()) {
                    'sqlite' => ' LIMIT -1',
                    'mysql' => ' LIMIT 18446744073709551615',
                    default => '',
                };
            }

            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }

    /** @return list<mixed> */
    public function getBindings(): array
    {
        $bindings = [];

        foreach ($this->wheres as $where) {
            if ($where['type'] === 'basic') {
                $bindings[] = $where['value'];
            } elseif (in_array($where['type'], ['in', 'not_in'], true)) {
                $bindings = [...$bindings, ...$where['values']];
            }
        }

        return $bindings;
    }

    private function compileWheres(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $clauses = [];

        foreach ($this->wheres as $where) {
            if ($where['type'] === 'in' || $where['type'] === 'not_in') {
                if ($where['values'] === []) {
                    $clauses[] = $where['type'] === 'in' ? '0 = 1' : '1 = 1';
                    continue;
                }

                $clauses[] = sprintf(
                    '%s %s (%s)',
                    $this->quoteIdentifier($where['column']),
                    $where['type'] === 'in' ? 'IN' : 'NOT IN',
                    implode(', ', array_fill(0, count($where['values']), '?')),
                );
                continue;
            }

            $column = $this->quoteIdentifier($where['column']);
            $clauses[] = match ($where['type']) {
                'null' => $column . ' IS NULL',
                'not_null' => $column . ' IS NOT NULL',
                default => sprintf('%s %s ?', $column, $where['operator']),
            };
        }

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    /** @param list<mixed> $values */
    private function addWhereIn(string $column, array $values, bool $not): self
    {
        $this->quoteIdentifier($column);
        $this->wheres[] = [
            'type' => $not ? 'not_in' : 'in',
            'column' => $column,
            'values' => array_values($values),
        ];

        return $this;
    }

    private function unqualifyColumn(string $column): string
    {
        $separator = strrpos($column, '.');

        return $separator === false ? $column : substr($column, $separator + 1);
    }

    /**
     * @param array<string, mixed> $values
     * @return array{string, list<mixed>}
     */
    private function compileInsert(array $values): array
    {
        if ($values === []) {
            throw new InvalidArgumentException('Insert values cannot be empty.');
        }

        $quotedColumns = array_map(
            fn (string $column): string => $this->quoteIdentifier($column),
            array_keys($values),
        );
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($this->table),
            implode(', ', $quotedColumns),
            implode(', ', array_fill(0, count($values), '?')),
        );

        return [$sql, array_values($values)];
    }

    private function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*') {
            return '*';
        }

        $segments = explode('.', $identifier);

        foreach ($segments as $segment) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $segment) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid SQL identifier "%s".', $identifier));
            }
        }

        $quote = $this->connection->driver() === 'mysql' ? '`' : '"';

        return implode('.', array_map(
            static fn (string $segment): string => $quote . $segment . $quote,
            $segments,
        ));
    }
}
