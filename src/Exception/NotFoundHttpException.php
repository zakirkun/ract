<?php

declare(strict_types=1);

namespace Ract\Exception;

final class NotFoundHttpException extends HttpException
{
    public function __construct(string $message = 'Page not found.')
    {
        parent::__construct(404, $message);
    }
}
