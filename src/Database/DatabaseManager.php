<?php

declare(strict_types=1);

namespace Ract\Database;

use InvalidArgumentException;
use PDO;
use Ract\Config\Config;
use RuntimeException;

final class DatabaseManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    public function __construct(private readonly Config $config)
    {
    }

    public function connection(?string $name = null): Connection
    {
        $name ??= (string) $this->config->get('database.default', 'sqlite');

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        $configuration = $this->config->get('database.connections.' . $name);

        if (!is_array($configuration)) {
            throw new InvalidArgumentException(sprintf('Database connection "%s" is not configured.', $name));
        }

        return $this->connections[$name] = new Connection($this->createPdo($name, $configuration));
    }

    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return $this->connection($connection)->table($table);
    }

    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        return $this->connection($connection)->transaction($callback);
    }

    public function purge(?string $name = null): void
    {
        $name ??= (string) $this->config->get('database.default', 'sqlite');
        unset($this->connections[$name]);
    }

    /** @param array<string, mixed> $configuration */
    private function createPdo(string $name, array $configuration): PDO
    {
        $driver = (string) ($configuration['driver'] ?? '');
        $username = isset($configuration['username']) ? (string) $configuration['username'] : null;
        $password = isset($configuration['password']) ? (string) $configuration['password'] : null;
        $options = $configuration['options'] ?? [];

        if (!is_array($options)) {
            throw new InvalidArgumentException(sprintf('Options for database connection "%s" must be an array.', $name));
        }

        $dsn = match ($driver) {
            'sqlite' => $this->sqliteDsn($name, $configuration),
            'mysql' => $this->mysqlDsn($configuration),
            'pgsql' => $this->pgsqlDsn($configuration),
            default => throw new InvalidArgumentException(sprintf(
                'Database connection "%s" uses unsupported driver "%s".',
                $name,
                $driver,
            )),
        };

        try {
            return new PDO($dsn, $username, $password, $options + [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $exception) {
            throw new RuntimeException(sprintf(
                'Could not connect to database "%s": %s',
                $name,
                $exception->getMessage(),
            ), 0, $exception);
        }
    }

    /** @param array<string, mixed> $configuration */
    private function sqliteDsn(string $name, array $configuration): string
    {
        $database = (string) ($configuration['database'] ?? '');

        if ($database === '') {
            throw new InvalidArgumentException(sprintf('SQLite connection "%s" requires a database path.', $name));
        }

        if ($database !== ':memory:') {
            $directory = dirname($database);

            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Could not create SQLite directory "%s".', $directory));
            }
        }

        return 'sqlite:' . $database;
    }

    /** @param array<string, mixed> $configuration */
    private function mysqlDsn(array $configuration): string
    {
        $host = (string) ($configuration['host'] ?? '127.0.0.1');
        $port = (int) ($configuration['port'] ?? 3306);
        $database = (string) ($configuration['database'] ?? '');
        $charset = (string) ($configuration['charset'] ?? 'utf8mb4');

        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
    }

    /** @param array<string, mixed> $configuration */
    private function pgsqlDsn(array $configuration): string
    {
        $host = (string) ($configuration['host'] ?? '127.0.0.1');
        $port = (int) ($configuration['port'] ?? 5432);
        $database = (string) ($configuration['database'] ?? '');

        return sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);
    }
}
