<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);
$databaseName = getenv('DB_DATABASE');
$sqliteDatabase = $databaseName === false || $databaseName === ''
    ? $rootPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite'
    : $databaseName;
$serverDatabase = $databaseName === false || $databaseName === '' ? 'ract' : $databaseName;

return [
    'default' => getenv('DB_CONNECTION') ?: 'sqlite',
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $sqliteDatabase,
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'database' => $serverDatabase,
            'username' => getenv('DB_USERNAME') ?: 'root',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => getenv('DB_CHARSET') ?: 'utf8mb4',
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('DB_PORT') ?: 5432),
            'database' => $serverDatabase,
            'username' => getenv('DB_USERNAME') ?: 'postgres',
            'password' => getenv('DB_PASSWORD') ?: '',
        ],
    ],
];
