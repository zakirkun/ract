<?php

declare(strict_types=1);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$publicPath = realpath(__DIR__);
$file = false;

if (is_string($path)
    && preg_match('/%(?![0-9A-Fa-f]{2})/', $path) !== 1
    && stripos($path, '%2f') === false
    && stripos($path, '%5c') === false
) {
    $decodedPath = rawurldecode($path);

    if (!str_contains($decodedPath, "\0")) {
        $candidate = __DIR__ . DIRECTORY_SEPARATOR . ltrim(
            str_replace('/', DIRECTORY_SEPARATOR, $decodedPath),
            DIRECTORY_SEPARATOR,
        );
        $file = realpath($candidate);
    }
}

$isPublicFile = false;

if ($publicPath !== false && $file !== false && is_file($file)) {
    $publicPrefix = $publicPath . DIRECTORY_SEPARATOR;
    $comparison = PHP_OS_FAMILY === 'Windows'
        ? strncasecmp($file, $publicPrefix, strlen($publicPrefix))
        : strncmp($file, $publicPrefix, strlen($publicPrefix));
    $isPublicFile = $comparison === 0;
}

if ($path !== '/' && $isPublicFile) {
    return false;
}

require __DIR__ . DIRECTORY_SEPARATOR . 'index.php';
