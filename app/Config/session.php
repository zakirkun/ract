<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);
$secure = getenv('SESSION_SECURE_COOKIE');
$httpOnly = getenv('SESSION_HTTP_ONLY');

return [
    'driver' => getenv('SESSION_DRIVER') ?: 'file',
    'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 120),
    'files' => getenv('SESSION_FILES') ?: $rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'sessions',
    'cookie' => getenv('SESSION_COOKIE') ?: 'ract_session',
    'path' => getenv('SESSION_PATH') ?: '/',
    'domain' => getenv('SESSION_DOMAIN') ?: null,
    'secure' => $secure === false ? false : filter_var($secure, FILTER_VALIDATE_BOOL),
    'http_only' => $httpOnly === false ? true : filter_var($httpOnly, FILTER_VALIDATE_BOOL),
    'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',
];
