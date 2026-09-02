<?php

declare(strict_types=1);

namespace Ract\Routing;

use InvalidArgumentException;

final class Route
{
    /** @var list<string> */
    private array $methods;

    private string $uri;

    private string $pattern;

    /** @var list<string> */
    private array $parameterNames = [];

    private ?string $routeName = null;

    /**
     * @param list<string> $methods
     * @param callable|array{class-string, string}|string $handler
     */
    public function __construct(array $methods, string $uri, private readonly mixed $handler)
    {
        if ($methods === []) {
            throw new InvalidArgumentException('A route must accept at least one HTTP method.');
        }

        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
        $this->uri = self::normalizeRouteUri($uri);
        $this->pattern = $this->compilePattern($this->uri);

        if (@preg_match($this->pattern, '/') === false) {
            throw new InvalidArgumentException(sprintf(
                'Route "%s" contains an invalid regular expression: %s',
                $uri,
                preg_last_error_msg(),
            ));
        }
    }

    public function name(string $name): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('A route name cannot be empty.');
        }

        $this->routeName = $name;

        return $this;
    }

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    /** @return list<string> */
    public function methods(): array
    {
        return $this->methods;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    /** @return callable|array{class-string, string}|string */
    public function handler(): mixed
    {
        return $this->handler;
    }

    public function supportsMethod(string $method): bool
    {
        $method = strtoupper($method);

        return in_array($method, $this->methods, true)
            || ($method === 'HEAD' && in_array('GET', $this->methods, true));
    }

    /** @return array<string, string>|null */
    public function matchPath(string $path): ?array
    {
        $normalizedPath = self::normalizeRequestPath($path);

        if ($normalizedPath === null || preg_match($this->pattern, $normalizedPath, $matches) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($this->parameterNames as $name) {
            $value = $matches[$name];

            if (str_contains($value, '/')) {
                return null;
            }

            $parameters[$name] = $value;
        }

        return $parameters;
    }

    private static function normalizeRouteUri(string $uri): string
    {
        if ($uri === '' || str_contains($uri, "\0")) {
            throw new InvalidArgumentException('A route URI cannot be empty or contain null bytes.');
        }

        $uri = '/' . trim($uri, '/');

        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    private static function normalizeRequestPath(string $uri): ?string
    {
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path)) {
            return null;
        }

        $path = '/' . trim($path, '/');

        if ($path === '/') {
            return '/';
        }

        $decodedSegments = [];

        foreach (explode('/', trim($path, '/')) as $segment) {
            if (preg_match('/%(?![0-9A-Fa-f]{2})/', $segment) === 1
                || stripos($segment, '%2f') !== false
                || stripos($segment, '%5c') !== false
            ) {
                return null;
            }

            $decodedSegment = rawurldecode($segment);

            if (str_contains($decodedSegment, "\0")
                || str_contains($decodedSegment, '/')
                || str_contains($decodedSegment, '\\')
                || preg_match('//u', $decodedSegment) !== 1
            ) {
                return null;
            }

            $decodedSegments[] = $decodedSegment;
        }

        return '/' . implode('/', $decodedSegments);
    }

    private function compilePattern(string $uri): string
    {
        if ($uri === '/') {
            return '#^/$#D';
        }

        $segments = explode('/', trim($uri, '/'));
        $compiled = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)(?::(.+))?\}$/', $segment, $matches) !== 1) {
                $compiled[] = preg_quote($segment);
                continue;
            }

            $name = $matches[1];

            if (in_array($name, $this->parameterNames, true)) {
                throw new InvalidArgumentException(sprintf('Duplicate route parameter "%s".', $name));
            }

            $this->parameterNames[] = $name;
            $expression = $matches[2] ?? '[^/]+';
            $compiled[] = sprintf('(?P<%s>%s)', $name, $expression);
        }

        $expression = '^/' . implode('/', $compiled) . '\\z';

        foreach (['~', '#', '%', '!', '@', ';', '`'] as $delimiter) {
            if (!str_contains($expression, $delimiter)) {
                return $delimiter . $expression . $delimiter . 'uD';
            }
        }

        throw new InvalidArgumentException(sprintf('Unable to compile route "%s".', $uri));
    }
}
