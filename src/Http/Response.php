<?php

declare(strict_types=1);

namespace Ract\Http;

use InvalidArgumentException;

final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{name: string, value: string}> */
    private array $additionalHeaders = [];

    /** @param array<string, string> $headers */
    public function __construct(
        private string $body = '',
        private int $statusCode = 200,
        array $headers = [],
    ) {
        $this->assertValidStatus($statusCode);

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    /** @param array<string, string> $headers */
    public static function html(string $body, int $statusCode = 200, array $headers = []): self
    {
        return new self($body, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8', ...$headers]);
    }

    /** @param array<string, string> $headers */
    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return new self($body, $statusCode, ['Content-Type' => 'application/json; charset=UTF-8', ...$headers]);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }

    public function setStatus(int $statusCode): self
    {
        $this->assertValidStatus($statusCode);
        $this->statusCode = $statusCode;

        return $this;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function setHeader(string $name, string $value): self
    {
        self::assertValidHeader($name, $value);

        foreach (array_keys($this->headers) as $existingName) {
            if (strcasecmp($existingName, $name) === 0) {
                unset($this->headers[$existingName]);
                break;
            }
        }

        $this->additionalHeaders = array_values(array_filter(
            $this->additionalHeaders,
            static fn (array $header): bool => strcasecmp($header['name'], $name) !== 0,
        ));
        $this->headers[$name] = $value;

        return $this;
    }

    public function addHeader(string $name, string $value): self
    {
        self::assertValidHeader($name, $value);

        foreach (array_keys($this->headers) as $existingName) {
            if (strcasecmp($existingName, $name) === 0) {
                $this->additionalHeaders[] = ['name' => $existingName, 'value' => $value];

                return $this;
            }
        }

        $this->headers[$name] = $value;

        return $this;
    }

    public static function assertValidHeader(string $name, string $value): void
    {
        if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/D', $name) !== 1) {
            throw new InvalidArgumentException('Response header names must use valid HTTP token characters.');
        }

        if (preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Response header values may not contain control characters.');
        }
    }

    public function header(string $name): ?string
    {
        return $this->headerValues($name)[0] ?? null;
    }

    /** @return list<string> */
    public function headerValues(string $name): array
    {
        $values = [];

        foreach ($this->headers as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                $values[] = $value;
                break;
            }
        }

        foreach ($this->additionalHeaders as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                $values[] = $header['value'];
            }
        }

        return $values;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function send(bool $sendBody = true): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }

            foreach ($this->additionalHeaders as $header) {
                header($header['name'] . ': ' . $header['value'], false);
            }
        }

        if ($sendBody) {
            echo $this->body;
        }
    }

    private function assertValidStatus(int $statusCode): void
    {
        if ($statusCode < 100 || $statusCode > 599) {
            throw new InvalidArgumentException('HTTP status codes must be between 100 and 599.');
        }
    }
}
