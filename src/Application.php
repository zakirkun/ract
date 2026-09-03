<?php

declare(strict_types=1);

namespace Ract;

use Closure;
use LogicException;
use Ract\Config\Config;
use Ract\Container\Container;
use Ract\Exception\HttpException;
use Ract\Http\Middleware;
use Ract\Http\Request;
use Ract\Http\Response;
use Ract\Routing\MatchedRoute;
use Ract\Routing\Router;
use Ract\Support\Facades\Facade;
use Ract\Support\ServiceProvider;
use Ract\Validation\ValidationException;
use Ract\View\View;
use Throwable;

final class Application
{
    private readonly Container $container;

    /** @var list<ServiceProvider> */
    private array $providers = [];

    private bool $providersBooted = false;

    /** @var list<string|Middleware> */
    private array $middleware = [];

    /** @var array<string, class-string<Middleware>> */
    private array $middlewareAliases = [];

    public function __construct(
        private readonly string $rootPath,
        private readonly Config $config,
        private readonly Router $router,
        private readonly View $view,
        ?Container $container = null,
    ) {
        $this->container = $container ?? new Container();
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);
        $this->container->instance(Config::class, $this->config);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(View::class, $this->view);
        Facade::setContainer($this->container);

        $timezone = (string) $this->config->get('app.timezone', 'UTC');

        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $exception) {
            throw new LogicException(sprintf('Invalid application timezone "%s".', $timezone), 0, $exception);
        }

        date_default_timezone_set($timezone);
    }

    public static function create(string $rootPath): self
    {
        $rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);
        $config = Config::loadDirectory($rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config');

        $app = new self(
            $rootPath,
            $config,
            new Router(),
            new View(
                $rootPath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views',
                $rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'views',
            ),
        );
        $providers = $config->get('app.providers', []);

        if (!is_array($providers)) {
            throw new LogicException('The app.providers configuration value must be an array.');
        }

        foreach ($providers as $provider) {
            if (!is_string($provider)) {
                throw new LogicException('Configured service providers must be class names.');
            }

            $app->registerProvider($provider);
        }

        $app->bootProviders();
        $aliases = $config->get('app.middleware_aliases', []);
        $middleware = $config->get('app.middleware', []);

        if (!is_array($aliases) || !is_array($middleware)) {
            throw new LogicException('The app.middleware and app.middleware_aliases configuration values must be arrays.');
        }

        foreach ($aliases as $alias => $class) {
            if (!is_string($alias) || !is_string($class)) {
                throw new LogicException('Configured middleware aliases must map strings to class names.');
            }

            $app->middlewareAlias($alias, $class);
        }

        $app->middleware($middleware);

        return $app;
    }

    public function run(): void
    {
        $request = Request::fromGlobals();
        $this->handle($request)->send($request->method() !== 'HEAD');
    }

    public function handle(Request $request): Response
    {
        $this->container->instance(Request::class, $request);
        $destination = function (Request $currentRequest): Response {
            try {
                $matchedRoute = $this->router->dispatch($currentRequest->method(), $currentRequest->path());
                $routeDestination = fn (Request $routeRequest): Response => $this->normalizeResponse($this->invoke($matchedRoute));

                return $this->sendThroughMiddleware(
                    $currentRequest,
                    $matchedRoute->route()->middlewareStack(),
                    $routeDestination,
                );
            } catch (Throwable $exception) {
                return $this->exceptionResponse($currentRequest, $exception);
            }
        };

        try {
            return $this->sendThroughMiddleware($request, $this->middleware, $destination);
        } catch (Throwable $exception) {
            return $this->exceptionResponse($request, $exception);
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

    public function container(): Container
    {
        return $this->container;
    }

    /** @param string|Middleware|list<string|Middleware> $middleware */
    public function middleware(string|Middleware|array $middleware): self
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];

        foreach ($middleware as $item) {
            if (!is_string($item) && !$item instanceof Middleware) {
                throw new LogicException('Application middleware must be an alias, class name, or Middleware instance.');
            }

            $this->middleware[] = $item;
        }

        return $this;
    }

    /** @param class-string<Middleware> $middleware */
    public function middlewareAlias(string $alias, string $middleware): self
    {
        if ($alias === '') {
            throw new LogicException('A middleware alias cannot be empty.');
        }

        if (!is_subclass_of($middleware, Middleware::class)) {
            throw new LogicException(sprintf('Middleware "%s" must implement %s.', $middleware, Middleware::class));
        }

        $this->middlewareAliases[$alias] = $middleware;

        return $this;
    }

    /** @param class-string<ServiceProvider>|ServiceProvider $provider */
    public function registerProvider(string|ServiceProvider $provider): self
    {
        if (is_string($provider)) {
            if (!is_subclass_of($provider, ServiceProvider::class)) {
                throw new LogicException(sprintf('Service provider "%s" must extend %s.', $provider, ServiceProvider::class));
            }

            $provider = $this->container->make($provider);
        }

        $provider->register();
        $this->providers[] = $provider;

        if ($this->providersBooted) {
            $provider->boot();
        }

        return $this;
    }

    public function bootProviders(): void
    {
        if ($this->providersBooted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->providersBooted = true;
    }

    /**
     * @param list<string|Middleware> $middleware
     * @param Closure(Request): Response $destination
     */
    private function sendThroughMiddleware(Request $request, array $middleware, Closure $destination): Response
    {
        $next = $destination;

        foreach (array_reverse($middleware) as $item) {
            $resolved = $this->resolveMiddleware($item);
            $next = fn (Request $currentRequest): Response => $this->runMiddleware($resolved, $currentRequest, $next);
        }

        return $next($request);
    }

    /** @param Closure(Request): Response $next */
    private function runMiddleware(Middleware $middleware, Request $request, Closure $next): Response
    {
        try {
            return $middleware->handle($request, $next);
        } catch (Throwable $exception) {
            return $this->exceptionResponse($request, $exception);
        }
    }

    private function resolveMiddleware(string|Middleware $middleware): Middleware
    {
        if ($middleware instanceof Middleware) {
            return $middleware;
        }

        $class = $this->middlewareAliases[$middleware] ?? $middleware;

        if (!is_subclass_of($class, Middleware::class)) {
            throw new LogicException(sprintf('Middleware "%s" is not a registered alias or Middleware class.', $middleware));
        }

        $resolved = $this->container->make($class);

        if (!$resolved instanceof Middleware) {
            throw new LogicException(sprintf('Container resolution for middleware "%s" returned an invalid object.', $class));
        }

        return $resolved;
    }

    private function exceptionResponse(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof ValidationException) {
            if ($request->expectsJson()) {
                return Response::json([
                    'message' => $exception->getMessage(),
                    'errors' => $exception->errors(),
                ], 422);
            }

            if ($request->hasSession()) {
                $request->session()->flash('_errors', $exception->errors());
                $request->session()->flash('_old_input', $this->safeOldInput($request->all()));
                $target = $this->validationRedirectTarget($request);

                if ($target !== null) {
                    return Response::redirect($target);
                }
            }
        }

        if ($exception instanceof HttpException) {
            return $this->safeErrorResponse(
                $exception->statusCode(),
                $exception->getMessage(),
                $exception->headers(),
                $exception,
            );
        }

        return $this->safeErrorResponse(500, 'An unexpected error occurred.', [], $exception);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    private function safeOldInput(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($key) && str_contains(strtolower($key), 'password')) {
                unset($input[$key]);
                continue;
            }

            if (is_array($value)) {
                $input[$key] = $this->safeOldInput($value);
            }
        }

        return $input;
    }

    private function validationRedirectTarget(Request $request): ?string
    {
        $referer = $request->header('Referer');

        if (!is_string($referer) || $referer === '') {
            return null;
        }

        $parts = parse_url($referer);

        if ($parts === false || (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
            return null;
        }

        if (isset($parts['host'])) {
            $requestHost = parse_url('http://' . (string) $request->header('Host', ''), PHP_URL_HOST);

            if (!is_string($requestHost) || strcasecmp($requestHost, $parts['host']) !== 0) {
                return null;
            }
        }

        $path = $parts['path'] ?? '/';

        if (!is_string($path) || !str_starts_with($path, '/')) {
            return null;
        }

        return isset($parts['query']) ? $path . '?' . $parts['query'] : $path;
    }

    private function invoke(MatchedRoute $matchedRoute): mixed
    {
        $handler = $matchedRoute->route()->handler();

        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $class = $handler[0];
            $method = $handler[1];

            if (!is_subclass_of($class, Controller::class)) {
                throw new LogicException(sprintf('Controller "%s" must extend %s.', $class, Controller::class));
            }

            $controller = $this->container->make($class);

            if (!is_callable([$controller, $method])) {
                throw new LogicException(sprintf('Controller action "%s::%s" is not callable.', $class, $method));
            }

            return $this->container->call([$controller, $method], $matchedRoute->parameters());
        }

        if (!is_callable($handler)) {
            throw new LogicException('The matched route handler is not callable.');
        }

        return $this->container->call($handler, $matchedRoute->parameters());
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
