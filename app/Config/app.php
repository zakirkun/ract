<?php

declare(strict_types=1);

use Ract\Database\DatabaseServiceProvider;

$debug = getenv('APP_DEBUG');

return [
    'name' => getenv('APP_NAME') ?: 'Ract',
    'environment' => getenv('APP_ENV') ?: 'development',
    'debug' => $debug === false ? true : filter_var($debug, FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'UTC',
    'base_url' => getenv('APP_URL') ?: 'http://localhost:8080',
    'providers' => [
        DatabaseServiceProvider::class,
    ],
];
