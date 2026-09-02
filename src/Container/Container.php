<?php

declare(strict_types=1);

namespace Ract\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class Container
{
    /** @var array<string, array{concrete: Closure|string, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, string> */
    private array $aliases = [];

    /** @var list<string> */
    private array $resolving = [];

    public function bind(string $abstract, Closure|string|null $concrete = null, bool $shared = false): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => $shared,
        ];
        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, object $instance): object
    {
        $this->instances[$abstract] = $instance;

        return $instance;
    }

    public function alias(string $abstract, string $alias): void
    {
        if ($abstract === $alias) {
            throw new BindingResolutionException('A container binding cannot alias itself.');
        }

        $this->aliases[$alias] = $abstract;
    }

    public function has(string $abstract): bool
    {
        $abstract = $this->resolveAlias($abstract);

        return isset($this->instances[$abstract])
            || isset($this->bindings[$abstract])
            || class_exists($abstract);
    }

    public function make(string $abstract): object
    {
        $abstract = $this->resolveAlias($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (in_array($abstract, $this->resolving, true)) {
            $chain = implode(' -> ', [...$this->resolving, $abstract]);
            throw new BindingResolutionException(sprintf('Circular dependency detected: %s.', $chain));
        }

        $binding = $this->bindings[$abstract] ?? [
            'concrete' => $abstract,
            'shared' => false,
        ];
        $this->resolving[] = $abstract;

        try {
            $concrete = $binding['concrete'];
            $object = $concrete instanceof Closure
                ? $concrete($this)
                : $this->build($concrete);
        } finally {
            array_pop($this->resolving);
        }

        if (!is_object($object)) {
            throw new BindingResolutionException(sprintf('Container binding "%s" did not resolve to an object.', $abstract));
        }

        if ($binding['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * @param callable|array{class-string|object, string} $callback
     * @param array<int|string, mixed> $parameters
     */
    public function call(callable|array $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);

            if ($reflection->isStatic()) {
                $callable = [$callback[0], $callback[1]];
            } else {
                $target = is_string($callback[0]) ? $this->make($callback[0]) : $callback[0];
                $callable = [$target, $callback[1]];
            }
        } else {
            $reflection = new ReflectionFunction(Closure::fromCallable($callback));
            $callable = $callback;
        }

        return $callable(...$this->resolveParameters($reflection, $parameters));
    }

    private function build(string $concrete): object
    {
        try {
            $reflection = new ReflectionClass($concrete);
        } catch (ReflectionException $exception) {
            throw new BindingResolutionException(sprintf('Target class "%s" does not exist.', $concrete), 0, $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new BindingResolutionException(sprintf('Target "%s" is not instantiable.', $concrete));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        return $reflection->newInstanceArgs($this->resolveParameters($constructor));
    }

    /**
     * @param array<int|string, mixed> $provided
     * @return list<mixed>
     */
    private function resolveParameters(ReflectionFunctionAbstract $reflection, array $provided = []): array
    {
        $resolved = [];
        $positional = array_values(array_filter(
            $provided,
            static fn (mixed $value, int|string $key): bool => is_int($key),
            ARRAY_FILTER_USE_BOTH,
        ));

        foreach ($reflection->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $provided)) {
                $resolved[] = $provided[$parameter->getName()];
                continue;
            }

            if ($parameter->isVariadic()) {
                $resolved = [...$resolved, ...$positional];
                $positional = [];
                continue;
            }

            if ($positional !== []) {
                $resolved[] = array_shift($positional);
                continue;
            }

            $class = $this->parameterClass($parameter);

            if ($class !== null) {
                if (!$this->canResolve($class)) {
                    if ($parameter->isDefaultValueAvailable()) {
                        $resolved[] = $parameter->getDefaultValue();
                        continue;
                    }

                    if ($parameter->allowsNull()) {
                        $resolved[] = null;
                        continue;
                    }
                }

                $resolved[] = $this->make($class);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $resolved[] = null;
                continue;
            }

            throw new BindingResolutionException(sprintf(
                'Unable to resolve parameter "$%s" while calling %s.',
                $parameter->getName(),
                $this->callableName($reflection),
            ));
        }

        return $resolved;
    }

    private function parameterClass(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin()
            ? $type->getName()
            : null;
    }

    private function canResolve(string $abstract): bool
    {
        $abstract = $this->resolveAlias($abstract);

        if (isset($this->instances[$abstract]) || isset($this->bindings[$abstract])) {
            return true;
        }

        if (!class_exists($abstract)) {
            return false;
        }

        try {
            return (new ReflectionClass($abstract))->isInstantiable();
        } catch (ReflectionException) {
            return false;
        }
    }

    private function callableName(ReflectionFunctionAbstract $reflection): string
    {
        return $reflection instanceof ReflectionMethod
            ? $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName()
            : $reflection->getName();
    }

    private function resolveAlias(string $abstract): string
    {
        $seen = [];

        while (isset($this->aliases[$abstract])) {
            if (in_array($abstract, $seen, true)) {
                throw new BindingResolutionException('Circular container alias detected.');
            }

            $seen[] = $abstract;
            $abstract = $this->aliases[$abstract];
        }

        return $abstract;
    }
}
