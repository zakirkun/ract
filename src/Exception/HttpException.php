<?php

declare(strict_types=1);

namespace Ract\Exception;

use InvalidArgumentException;
use Ract\Http\Response;
use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        private readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        if ($statusCode < 400 || $statusCode > 599) {
            throw new InvalidArgumentException('HTTP exceptions must use a status code between 400 and 599.');
        }

        foreach ($headers as $name => $value) {
            Response::assertValidHeader($name, $value);
        }

        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
