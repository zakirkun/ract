<?php

declare(strict_types=1);

namespace App\Controllers;

use Ract\Controller;
use Ract\Http\Response;

final class HomeController extends Controller
{
    public function index(): Response
    {
        return $this->view('home', [
            'framework' => $this->config->get('app.name', 'Ract'),
            'environment' => $this->config->get('app.environment', 'production'),
        ], 'layouts/main');
    }

    public function hello(string $name): Response
    {
        return $this->view('hello', [
            'name' => $name,
        ], 'layouts/main');
    }

    public function status(): Response
    {
        return $this->json([
            'framework' => 'Ract',
            'status' => 'ok',
            'php' => PHP_VERSION,
        ]);
    }
}
