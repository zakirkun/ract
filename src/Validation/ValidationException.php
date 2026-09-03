<?php

declare(strict_types=1);

namespace Ract\Validation;

use Ract\Exception\HttpException;

final class ValidationException extends HttpException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(422, 'The given data was invalid.');
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
