<?php

declare(strict_types=1);

namespace Ract;

use LogicException;
use Ract\Config\Config;
use Ract\Exception\HttpException;
use Ract\Http\Request;
use Ract\Http\Response;
use Ract\Routing\MatchedRoute;
use Ract\Routing\Router;
use Ract\View\View;
use Throwable;

final class Application
{
    public function __construct(
        private readonly string $rootPath,
        private readonly Config $config,
        private readonly Router $router,
        private readonly View $view,
    ) {
        $timezone = (string) $this->config->get('app.timezone', 'UTC');
        date_default_timezone_set($timezone);
    }

    public static function create(string $rootPath): self
    {
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $config = Config::loadDirectory($rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config');

        return new self(
            $rootPath,
            $config,
            new Router(),
            new View($rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views'),
        );
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $this->handle($request)->send($request->method() !== 'HEAD');
    }

    public function handle(Request $request): Response
    {
        try {
            $matchedRoute = $this->router->dispatch($request->method(), $request->path());

            return $this->normalizeResponse($this->invoke($matchedRoute, $request));
        } catch (HttpException $exception) {
            return $this->safeErrorResponse(
                $exception->statusCode(),
                $exception->getMessage(),
                $exception->headers(),
                $exception,
            );
        } catch (Throwable $exception) {
            return $this->safeErrorResponse(500, 'An unexpected error occurred.', [], $exception);
        }
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function rootPath(): string
    {
        return $this->rootPath;
    }

    private function invoke(MatchedRoute $matchedRoute, Request $request): mixed
    {
        $handler = $matchedRoute->route()->handler();
        $parameters = array_values($matchedRoute->parameters());

        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $class = $handler[0];
            $method = $handler[1];

            if (!is_subclass_of($class, Controller::class)) {
                throw new LogicException(sprintf('Controller "%s" must extend %s.', $class, Controller::class));
            }

            $controller = new $class($request, $this->config, $this->view);

            if (!is_callable([$controller, $method])) {
                throw new LogicException(sprintf('Controller action "%s::%s" is not callable.', $class, $method));
            }

            return $controller->{$method}(...$parameters);
        }

        if (!is_callable($handler)) {
            throw new LogicException('The matched route handler is not callable.');
        }

        return $handler(...$parameters);
    }

    private function normalizeResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            is_array($result) => Response::json($result),
            is_string($result) => Response::html($result),
            $result === null => new Response('', 204),
            default => throw new LogicException('Route handlers must return a Response, array, string, or null.'),
        };
    }

    /** @param array<string, string> $headers */
    private function safeErrorResponse(
        int $statusCode,
        string $message,
        array $headers,
        Throwable $exception,
    ): Response {
        try {
            return $this->errorResponse($statusCode, $message, $headers, $exception);
        } catch (Throwable) {
            return Response::html(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>500</title></head><body><h1>500</h1><p>An unexpected error occurred.</p></body></html>',
                500,
            );
        }
    }

    /** @param array<string, string> $headers */
    private function errorResponse(
        int $statusCode,
        string $message,
        array $headers,
        Throwable $exception,
    ): Response {
        $debug = (bool) $this->config->get('app.debug', false);
        $displayMessage = $statusCode === 500 && !$debug ? 'An unexpected error occurred.' : $message;
        $viewName = 'errors/' . $statusCode;

        try {
            if (!$this->view->exists($viewName)) {
                $viewName = 'errors/error';
            }

            $body = $this->view->render($viewName, [
                'statusCode' => $statusCode,
                'message' => $displayMessage,
                'exception' => $debug ? $exception : null,
            ]);
        } catch (Throwable) {
            $body = sprintf(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>%d</title></head><body><h1>%d</h1><p>%s</p></body></html>',
                $statusCode,
                $statusCode,
                e($displayMessage),
            );
        }

        return Response::html($body, $statusCode, $headers);
    }
}
