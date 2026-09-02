<?php

declare(strict_types=1);

namespace Ract\Support\Facades;

use Ract\Database\Schema\SchemaBuilder;

final class Schema extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SchemaBuilder::class;
    }
}
