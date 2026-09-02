<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class ContainerService
{
    public function __construct(public readonly ContainerDependency $dependency)
    {
    }

    public function greet(string $name): string
    {
        return $this->dependency->message() . ', ' . $name;
    }
}
