<?php

declare(strict_types=1);

namespace Ract\Support\Facades;

use Ract\Container\Container;

final class App extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Container::class;
    }
}
