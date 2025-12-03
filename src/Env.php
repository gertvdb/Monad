<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use LogicException;

final class Env implements IEnv
{
    /** @var array<class-string, object> */
    private array $items;

    public function __construct(
        array $items = [],
    ) {
        $this->items = $items;
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Insert or replace a context item (immutable).
     */
    public function with(object $dependency): self
    {
        return new self(array_merge($this->items, [$dependency::class => $dependency]));
    }

    /**
     * Strict lookup. Error if missing.
     */
    public function read(string $class): object
    {
        if (!isset($this->items[$class])) {
            throw new LogicException("Missing required context: $class");
        }
        return $this->items[$class];
    }

    /**
     * Optional lookup.
     */
    public function get(string $class): ?object
    {
        return $this->items[$class] ?? null;
    }

    public function local(callable $fn, ?object $override = null): ?object
    {
        $env = $override ? $this->with($override) : $this;
        return $fn($env);
    }

    public function merge(IEnv $other): self
    {
        return new self(array_merge($this->items, $other->items));
    }

    public function all(): array
    {
        return $this->items;
    }
}
