<?php

declare(strict_types=1);

namespace Ract\Support;

use Ract\Container\Container;

abstract class ServiceProvider
{
    public function __construct(protected readonly Container $app)
    {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
