<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Ract\Routing\Router;

return static function (Router $router): void {
    $router->get('/api/status', [HomeController::class, 'status'])->name('api.status');
};
