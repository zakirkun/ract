<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Ract\Routing\Router;

return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index'])->name('home');
    $router->get('/hello/{name}', [HomeController::class, 'hello'])->name('hello');

    $routeFiles = glob(__DIR__ . DIRECTORY_SEPARATOR . 'Routes' . DIRECTORY_SEPARATOR . '*.php') ?: [];
    sort($routeFiles, SORT_STRING);

    foreach ($routeFiles as $routeFile) {
        $routes = require $routeFile;

        if (!is_callable($routes)) {
            throw new RuntimeException(sprintf('Route file "%s" must return a callable.', $routeFile));
        }

        $routes($router);
    }
};
