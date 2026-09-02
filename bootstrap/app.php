<?php

declare(strict_types=1);

use Ract\Application;

$rootPath = dirname(__DIR__);
$autoload = $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (!is_file($autoload)) {
    throw new RuntimeException('Composer dependencies are missing. Run "composer install" first.');
}

require $autoload;

$app = Application::create($rootPath);
$routes = require $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'routes.php';

if (!is_callable($routes)) {
    throw new RuntimeException('The app/routes.php file must return a callable.');
}

$routes($app->router());

return $app;
