<?php

declare(strict_types=1);

namespace Ract;

use Ract\Config\Config;
use Ract\Http\Request;
use Ract\Http\Response;
use Ract\View\View;

abstract class Controller
{
    public function __construct(
        protected readonly Request $request,
        protected readonly Config $config,
        protected readonly View $renderer,
    ) {
    }

    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = [], ?string $layout = null, int $statusCode = 200): Response
    {
        return Response::html($this->renderer->render($view, $data, $layout), $statusCode);
    }

    protected function json(mixed $data, int $statusCode = 200): Response
    {
        return Response::json($data, $statusCode);
    }

    protected function redirect(string $url, int $statusCode = 302): Response
    {
        return Response::redirect($url, $statusCode);
    }
}
