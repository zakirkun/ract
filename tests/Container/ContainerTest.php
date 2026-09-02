<?php

declare(strict_types=1);

namespace Tests\Container;

use PHPUnit\Framework\TestCase;
use Ract\Container\BindingResolutionException;
use Ract\Container\Container;
use Ract\Support\Facades\Facade;
use Ract\Support\ServiceProvider;
use Tests\Fixtures\ContainerDependency;
use Tests\Fixtures\ContainerService;
use Tests\Fixtures\StaticContainerService;
use Tests\Fixtures\TestFacade;

final class ContainerTest extends TestCase
{
    protected function tearDown(): void
    {
        Facade::clearContainer();
    }

    public function testItAutowiresClassesAndSharesSingletons(): void
    {
        $container = new Container();
        $container->singleton(ContainerDependency::class);

        $first = $container->make(ContainerService::class);
        $second = $container->make(ContainerService::class);

        self::assertInstanceOf(ContainerService::class, $first);
        self::assertSame($first->dependency, $second->dependency);
        self::assertNotSame($first, $second);
    }

    public function testItInjectsDependenciesWhenCallingHandlers(): void
    {
        $container = new Container();

        $result = $container->call(
            static fn (ContainerDependency $dependency, string $name): string => $dependency->message() . ':' . $name,
            ['name' => 'Ract'],
        );

        self::assertSame('resolved:Ract', $result);
    }

    public function testItCallsStaticClassHandlersWithoutConstructingTheirClass(): void
    {
        $container = new Container();

        self::assertSame(
            'resolved:Ract',
            $container->call([StaticContainerService::class, 'greet'], ['name' => 'Ract']),
        );
    }

    public function testServiceProvidersRegisterAndBootBindings(): void
    {
        $container = new Container();
        $provider = new class ($container) extends ServiceProvider {
            public bool $booted = false;

            public function register(): void
            {
                $this->app->singleton(ContainerDependency::class);
            }

            public function boot(): void
            {
                $this->booted = true;
            }
        };

        $provider->register();
        $provider->boot();

        self::assertTrue($provider->booted);
        self::assertSame(
            $container->make(ContainerDependency::class),
            $container->make(ContainerDependency::class),
        );
    }

    public function testFacadesProxyCallsToContainerServices(): void
    {
        $container = new Container();
        $container->singleton(ContainerService::class);
        Facade::setContainer($container);

        self::assertSame('resolved, Shiro', TestFacade::greet('Shiro'));
    }

    public function testOptionalUnboundInterfaceDependenciesUseTheirDefaults(): void
    {
        $container = new Container();
        $callback = static fn (?\Stringable $logger = null): ?\Stringable => $logger;

        self::assertNull($container->call($callback));

        $logger = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'logger';
            }
        };
        $container->instance(\Stringable::class, $logger);

        self::assertSame($logger, $container->call($callback));
    }

    public function testItReportsUnresolvablePrimitiveDependencies(): void
    {
        $container = new Container();

        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('value');

        $container->call(static fn (string $value): string => $value);
    }
}
