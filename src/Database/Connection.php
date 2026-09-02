<?php

declare(strict_types=1);

namespace Ract\Database;

use PDO;
use PDOStatement;
use Throwable;

class Connection
{
    private int $transactionDepth = 0;

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function driver(): string
    {
        return (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->run($sql, $bindings);
        $rows = $statement->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @param list<mixed> $bindings */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        $statement = $this->run($sql, $bindings);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param list<mixed> $bindings */
    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings)->rowCount() >= 0;
    }

    /** @param list<mixed> $bindings */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function lastInsertId(?string $sequence = null): string
    {
        return $this->pdo->lastInsertId($sequence);
    }

    public function transaction(callable $callback): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        $savepoint = null;

        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
            $this->transactionDepth = 1;
        } else {
            $depth = $this->transactionDepth + 1;
            $savepoint = 'ract_savepoint_' . $depth;
            $this->pdo->exec('SAVEPOINT ' . $savepoint);
            $this->transactionDepth = $depth;
        }

        try {
            $result = $callback($this);

            if ($this->pdo->inTransaction()) {
                if ($ownsTransaction) {
                    $this->pdo->commit();
                } else {
                    $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }

            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                } else {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                    $this->pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
                }
            }

            throw $exception;
        } finally {
            $this->transactionDepth = max(0, $this->transactionDepth - 1);
        }
    }

    /**
     * @param list<mixed> $bindings
     */
    private function run(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);

        foreach (array_values($bindings) as $index => $value) {
            $type = match (true) {
                $value === null => PDO::PARAM_NULL,
                is_bool($value) => PDO::PARAM_BOOL,
                is_int($value) => PDO::PARAM_INT,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($index + 1, $value, $type);
        }

        $statement->execute();

        return $statement;
    }
}
