<?php

declare(strict_types=1);

namespace Ract\Http;

use JsonException;
use LogicException;
use Ract\Exception\HttpException;
use Ract\Session\Session;
use Ract\Validation\Validator;

final class Request
{
    /** @var array<string, string> */
    private array $headers = [];

    private ?Session $session = null;

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
        $data = $this->requestData();

        return $key === null ? $data : ($data[$key] ?? $default);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $data = $this->requestData();

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        return array_key_exists($key, $this->query) ? $this->query[$key] : $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->requestData() + $this->query;
    }

    /**
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     */
    public function validate(array $rules, array $messages = []): array
    {
        return Validator::make($this->all(), $rules, $messages)->validate();
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

    public function setSession(Session $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function hasSession(): bool
    {
        return $this->session !== null;
    }

    public function session(): Session
    {
        if ($this->session === null) {
            throw new LogicException('No session has been started for this request.');
        }

        return $this->session;
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

        try {
            return json_decode($this->body, $associative, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HttpException(400, 'Malformed JSON request body.', [], $exception);
        }
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function expectsJson(): bool
    {
        $accept = strtolower((string) $this->header('Accept', ''));
        $contentType = strtolower((string) $this->header('Content-Type', ''));

        return $this->isAjax()
            || str_contains($accept, '/json')
            || str_contains($accept, '+json')
            || str_contains($contentType, '/json')
            || str_contains($contentType, '+json');
    }

    public function bearerToken(): ?string
    {
        $authorization = (string) $this->header('Authorization', '');

        return preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1
            ? $matches[1]
            : null;
    }

    /** @return array<string, mixed> */
    private function requestData(): array
    {
        if ($this->body === '') {
            return $this->data;
        }

        $contentType = strtolower(trim(explode(';', (string) $this->header('Content-Type', ''), 2)[0]));
        $bodyData = [];

        if ($contentType === 'application/json' || str_ends_with($contentType, '+json')) {
            $decoded = $this->json();
            $bodyData = is_array($decoded) ? $decoded : [];
        } elseif ($contentType === 'application/x-www-form-urlencoded') {
            parse_str($this->body, $bodyData);
        }

        return $this->data + $bodyData;
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
