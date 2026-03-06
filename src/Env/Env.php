<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Env;

use LogicException;
use ReflectionClass;

final class Env implements IEnv
{
    /** @var array<class-string, object> */
    private array $items;

    /** @var array<class-string, callable(self):object> */
    private array $factories;

    /** @var array<string, list<object>> */
    private array $tags;

    public function __construct(
        array $items = [],
        array $factories = [],
        array $tags = [],
    ) {
        $this->items = $items;
        $this->factories = $factories;
        $this->tags = $tags;
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * Insert or replace a dependency (immutable).
     */
    public function with(object $dependency): self
    {
        $clone = clone $this;
        $clone->items[$dependency::class] = $dependency;
        return $clone;
    }

    /**
     * Register a lazy factory.
     */
    public function withFactory(string $class, callable $factory): self
    {
        $clone = clone $this;
        $clone->factories[$class] = $factory;
        return $clone;
    }

    /**
     * Strict lookup. Error if missing.
     */
    public function read(string $class): object
    {
        if (isset($this->items[$class])) {
            return $this->items[$class];
        }

        if (isset($this->factories[$class])) {
            $service = ($this->factories[$class])($this);

            $clone = clone $this;
            $clone->items[$class] = $service;

            return $service;
        }

        throw new LogicException("Missing required context: $class");
    }

    /**
     * Optional lookup.
     */
    public function get(string $class): ?object
    {
        try {
            return $this->read($class);
        } catch (LogicException) {
            return null;
        }
    }

    /**
     * Check if a dependency exists.
     */
    public function has(string $class): bool
    {
        return isset($this->items[$class]) || isset($this->factories[$class]);
    }

    /**
     * Autowire a class via constructor reflection.
     */
    public function make(string $class): object
    {
        $ref = new ReflectionClass($class);

        $ctor = $ref->getConstructor();

        if (!$ctor) {
            return new $class();
        }

        $args = [];

        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();

            if (!$type) {
                throw new LogicException(
                    "Cannot autowire parameter \${$param->getName()} in $class"
                );
            }

            $args[] = $this->read($type->getName());
        }

        return new $class(...$args);
    }

    /**
     * Run code in a scoped environment with overrides.
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
     * Merge environments (other overrides current).
     */
    public function merge(IEnv $other): self
    {
        return new self(
            array_merge($this->items, $other->all()),
            $this->factories,
            $this->tags
        );
    }

    /**
     * Register tagged dependency.
     */
    public function tag(string $tag, object $service): self
    {
        $clone = clone $this;
        $clone->tags[$tag][] = $service;
        return $clone;
    }

    /**
     * Retrieve tagged dependencies.
     *
     * @return list<object>
     */
    public function tagged(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * Return all registered items.
     *
     * @return array<class-string, object>
     */
    public function all(): array
    {
        return $this->items;
    }
}
