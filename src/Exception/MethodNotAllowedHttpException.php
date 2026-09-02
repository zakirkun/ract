<?php

declare(strict_types=1);

namespace Ract\Exception;

final class MethodNotAllowedHttpException extends HttpException
{
    /** @var list<string> */
    private readonly array $allowedMethods;

    /** @param list<string> $allowedMethods */
    public function __construct(array $allowedMethods)
    {
        $this->allowedMethods = array_values(array_unique($allowedMethods));

        parent::__construct(
            405,
            'Method not allowed.',
            ['Allow' => implode(', ', $this->allowedMethods)],
        );
    }

    /** @return list<string> */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
