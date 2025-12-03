<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

interface IEnv
{
    /**
     * Return a new Env with a dependency added or replaced.
     */
    public function with(object $dependency): self;

    /**
     * Read a required dependency by class.
     * Throws if the dependency is missing.
     *
     * @template T
     * @param class-string<T> $class
     * @return T
     * @throws \LogicException
     */
    public function read(string $class): object;

    /**
     * Optionally read a dependency by class.
     *
     * @template T
     * @param class-string<T> $class
     * @return T|null
     */
    public function get(string $class): ?object;

    /**
     * Merge this Env with another Env.
     * Dependencies from the other Env override this Env if keys collide.
     */
    public function merge(IEnv $other): self;

    /**
     * Temporarily override dependencies for the scope of a computation.
     *
     * @param callable(self): mixed $fn
     * @return mixed
     */
    public function local(callable $fn): mixed;

    /**
     * Return all dependencies as an array (optional, mostly for debugging)
     *
     * @return array<class-string, object>
     */
    public function all(): array;
}
