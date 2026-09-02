<?php

declare(strict_types=1);

namespace Ract\Support\Facades;

use LogicException;
use Ract\Container\Container;

abstract class Facade
{
    private static ?Container $container = null;

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    public static function clearContainer(): void
    {
        self::$container = null;
    }

    final public static function __callStatic(string $method, array $arguments): mixed
    {
        if (self::$container === null) {
            throw new LogicException('A facade container has not been configured.');
        }

        $instance = self::$container->make(static::getFacadeAccessor());

        if (!is_callable([$instance, $method])) {
            throw new LogicException(sprintf(
                'Method "%s::%s" is not callable.',
                $instance::class,
                $method,
            ));
        }

        return $instance->{$method}(...$arguments);
    }

    abstract protected static function getFacadeAccessor(): string;
}
