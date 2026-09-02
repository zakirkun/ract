<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class ContainerDependency
{
    public function message(): string
    {
        return 'resolved';
    }
}
