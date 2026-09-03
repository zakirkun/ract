<?php

declare(strict_types=1);

namespace Ract\Routing;

use Ract\Exception\MethodNotAllowedHttpException;
use Ract\Exception\NotFoundHttpException;
use Ract\Http\Middleware;

final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var list<string|Middleware> */
    private array $groupMiddleware = [];

    /**
     * @param string|list<string> $methods
     * @param callable|array{class-string, string}|string $handler
     */
    public function add(string|array $methods, string $uri, mixed $handler): Route
    {
        if (is_string($methods)) {
            $methods = preg_split('/[|,]/', $methods) ?: [];
        }

        $route = new Route($methods, $uri, $handler);

        if ($this->groupMiddleware !== []) {
            $route->middleware($this->groupMiddleware);
        }

        $this->routes[] = $route;

        return $route;
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function get(string $uri, mixed $handler): Route
    {
        return $this->add('GET', $uri, $handler);
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function post(string $uri, mixed $handler): Route
    {
        return $this->add('POST', $uri, $handler);
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function put(string $uri, mixed $handler): Route
    {
        return $this->add('PUT', $uri, $handler);
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function patch(string $uri, mixed $handler): Route
    {
        return $this->add('PATCH', $uri, $handler);
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function delete(string $uri, mixed $handler): Route
    {
        return $this->add('DELETE', $uri, $handler);
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function options(string $uri, mixed $handler): Route
    {
        return $this->add('OPTIONS', $uri, $handler);
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function match(array $methods, string $uri, mixed $handler): Route
    {
        return $this->add($methods, $uri, $handler);
    }

    /** @param callable|array{class-string, string}|string $handler */
    public function any(string $uri, mixed $handler): Route
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], $uri, $handler);
    }

    /**
     * @param string|Middleware|list<string|Middleware> $middleware
     * @param callable(self): void $routes
     */
    public function middleware(string|Middleware|array $middleware, callable $routes): void
    {
        $previous = $this->groupMiddleware;
        $items = is_array($middleware) ? $middleware : [$middleware];

        foreach ($items as $item) {
            if (!is_string($item) && !$item instanceof Middleware) {
                throw new \InvalidArgumentException('Route group middleware must be an alias, class name, or Middleware instance.');
            }

            $this->groupMiddleware[] = $item;
        }

        try {
            $routes($this);
        } finally {
            $this->groupMiddleware = $previous;
        }
    }

    public function dispatch(string $method, string $path): MatchedRoute
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $parameters = $route->matchPath($path);

            if ($parameters === null) {
                continue;
            }

            if ($route->supportsMethod($method)) {
                return new MatchedRoute($route, $parameters);
            }

            $routeMethods = $route->methods();

            if (in_array('GET', $routeMethods, true)) {
                $routeMethods[] = 'HEAD';
            }

            $allowedMethods = [...$allowedMethods, ...$routeMethods];
        }

        if ($allowedMethods !== []) {
            throw new MethodNotAllowedHttpException($allowedMethods);
        }

        throw new NotFoundHttpException(sprintf('No route found for %s %s.', strtoupper($method), $path));
    }

    public function named(string $name): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->routeName() === $name) {
                return $route;
            }
        }

        return null;
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }
}
