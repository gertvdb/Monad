<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Env;

use LogicException;
use ReflectionClass;
use ReflectionNamedType;

final class Env implements IEnv
{
    /** @var array<class-string, object> */
    private array $items;

    /** @var array<class-string, callable(self):object> */
    private array $factories;

    /** @var array<class-string, class-string> */
    private array $bindings;

    /** @var array<string, list<object>> */
    private array $tags;

    /** @var array<string, mixed> */
    private array $parameters;

    /** reflection cache */
    private static array $constructorCache = [];

    private array $resolving = [];

    public function __construct(
        array $items = [],
        array $factories = [],
        array $bindings = [],
        array $tags = [],
        array $parameters = [],
    ) {
        $this->items = $items;
        $this->factories = $factories;
        $this->bindings = $bindings;
        $this->tags = $tags;
        $this->parameters = $parameters;
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Register existing instance
     */
    public function withService(object $dependency): self
    {
        $clone = clone $this;
        $clone->items[$dependency::class] = $dependency;
        return $clone;
    }

    /**
     * Bind interface → implementation
     */
    public function withAlias(string $alias, string $implementation): self
    {
        if (!class_exists($alias) && !interface_exists($alias)) {
            throw new LogicException("$alias is not a valid interface or class");
        }

        if (!class_exists($implementation)) {
            throw new LogicException("$implementation is not a valid class");
        }

        if (!is_subclass_of($implementation, $alias) && $implementation !== $alias) {
            throw new LogicException("$implementation must extend or implement $alias");
        }

        $clone = clone $this;
        $clone->bindings[$alias] = $implementation;

        return $clone;
    }

    /**
     * Lazy service factory
     */
    public function withFactory(string $class, callable $factory): self
    {
        $clone = clone $this;
        $clone->factories[$class] = $factory;
        return $clone;
    }

    /**
     * Parameter registration
     */
    public function withParam(string $name, string|int|float|bool|array $value): self
    {
        $clone = clone $this;
        $clone->parameters[$name] = $value;
        return $clone;
    }

    public function parameter(string $name): string|int|float|bool|array
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new LogicException("Missing parameter: $name");
        }

        return $this->parameters[$name];
    }

    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * Strict lookup
     */
    public function read(string $class): object
    {
        if (isset($this->items[$class])) {
            return $this->items[$class];
        }

        if (isset($this->resolving[$class])) {
            throw new LogicException("Circular dependency detected: $class");
        }

        $this->resolving[$class] = true;

        try {

            if (isset($this->factories[$class])) {
                $service = ($this->factories[$class])($this);

                if (!$service instanceof $class) {
                    throw new LogicException("Factory for $class did not return $class");
                }

                return $this->items[$class] = $service;
            }

            if (isset($this->bindings[$class])) {

                $impl = $this->bindings[$class];

                if (!class_exists($impl)) {
                    throw new LogicException("Binding for $class points to invalid class $impl");
                }

                $service = $this->make($impl);

                if (!$service instanceof $class) {
                    throw new LogicException("$impl must implement $class");
                }

                return $this->items[$class] = $service;
            }

            if (class_exists($class)) {

                $ref = new ReflectionClass($class);

                if (!$ref->isInstantiable()) {
                    throw new LogicException("Class $class is not instantiable");
                }

                return $this->items[$class] = $this->make($class);
            }

            throw new LogicException("Missing required context: $class");

        } finally {
            unset($this->resolving[$class]);
        }
    }

    /**
     * Optional lookup
     */
    public function get(string $class): ?object
    {
        try {
            return $this->read($class);
        } catch (LogicException) {
            return null;
        }
    }

    public function has(string $class): bool
    {
        return isset($this->items[$class])
            || isset($this->factories[$class])
            || isset($this->bindings[$class]);
    }

    /**
     * Autowire class
     */
    public function make(string $class): object
    {
        $ref = new ReflectionClass($class);

        if (!$ref->isInstantiable()) {
            throw new LogicException("Cannot instantiate $class");
        }

        $ctor = self::$constructorCache[$class]
            ??= $ref->getConstructor();

        if (!$ctor) {
            return new $class();
        }

        $args = [];

        foreach ($ctor->getParameters() as $param) {

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->read($type->getName());
                continue;
            }

            $name = $param->getName();

            if ($this->hasParameter($name)) {
                $args[] = $this->parameter($name);
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new LogicException(
                "Cannot resolve parameter \${$name} in $class"
            );
        }

        return $ref->newInstanceArgs($args);
    }

    /**
     * Scoped environment
     */
    public function local(callable $fn, object ...$overrides): mixed
    {
        $env = $this;

        foreach ($overrides as $override) {
            $env = $env->with($override);
        }

        return $fn($env);
    }

    /**
     * Merge environments
     */
    public function merge(IEnv $other): self
    {
        return new self(
            array_merge($this->items, $other->all()),
            $this->factories,
            $this->bindings,
            $this->tags,
            $this->parameters
        );
    }

    /**
     * Tagged services
     */
    public function withTag(string $tag, object $service): self
    {
        $clone = clone $this;
        $clone->tags[$tag][] = $service;
        return $clone;
    }

    /**
     * Retrieve tagged services
     *
     * @return list<object>
     */
    public function tagged(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * All instantiated services
     *
     * @return array<class-string, object>
     */
    public function all(): array
    {
        return $this->items;
    }
}
