<?php

declare(strict_types=1);

namespace Ract\Support\Facades;

use Ract\Database\DatabaseManager;

final class DB extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DatabaseManager::class;
    }
}
