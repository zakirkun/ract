<?php

declare(strict_types=1);

namespace Ract\Database;

use Ract\Exception\HttpException;

final class ModelNotFoundException extends HttpException
{
    /** @param class-string<Model> $model */
    public function __construct(string $model, int|string $id)
    {
        parent::__construct(404, sprintf('%s record "%s" was not found.', $model, $id));
    }
}
