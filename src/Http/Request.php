<?php

declare(strict_types=1);

namespace Ract\Http;

final class Request
{
    /** @var array<string, string> */
    private array $headers = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private string $method,
        private string $uri,
        private array $query = [],
        private array $data = [],
        array $headers = [],
        private array $cookies = [],
        private array $files = [],
        private array $server = [],
        private string $body = '',
    ) {
        $this->method = strtoupper($method);

        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = $value;
        }
    }

    public static function fromGlobals(): self
    {
        $server = $_SERVER;
        $headers = self::headersFromServer($server);
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST') {
            $override = $headers['X-Http-Method-Override'] ?? $_POST['_method'] ?? null;

            if (is_string($override) && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = strtoupper($override);
            }
        }

        return new self(
            $method,
            (string) ($server['REQUEST_URI'] ?? '/'),
            $_GET,
            $_POST,
            $headers,
            $_COOKIE,
            $_FILES,
            $server,
            (string) file_get_contents('php://input'),
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function uri(): string
    {
        return $this->uri;
    }

    public function path(): string
    {
        $path = parse_url($this->uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->data : ($this->data[$key] ?? $default);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $this->query[$key] ?? $default;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function json(bool $associative = true): mixed
    {
        if ($this->body === '') {
            return null;
        }

        return json_decode($this->body, $associative, 512, JSON_THROW_ON_ERROR);
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function bearerToken(): ?string
    {
        $authorization = (string) $this->header('Authorization', '');

        return preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1
            ? $matches[1]
            : null;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
