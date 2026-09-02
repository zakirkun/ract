<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Ract\Support\Facades\Facade;

final class TestFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ContainerService::class;
    }
}
