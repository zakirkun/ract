<?php

declare(strict_types=1);

namespace Tests\Fixtures;

abstract class StaticContainerService
{
    public static function greet(ContainerDependency $dependency, string $name): string
    {
        return $dependency->message() . ':' . $name;
    }
}
