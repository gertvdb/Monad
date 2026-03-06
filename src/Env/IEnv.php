<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Env;

use Psr\Container\ContainerInterface;

/**
 * Immutable environment (Reader) used to pass contextual dependencies.
 *
 * You can enrich it with services and read them by class-string. Implementations
 * in this package are simple and do not require a full DI container.
 */
interface IEnv
{
    /**
     * Return a new Env with a dependency added or replaced.
     *
     * ```
     * $env = $env->with(new Translator('en_US'));
     * ```
     */
    public function with(object $dependency): self;

    /**
     * Read a required dependency by class.
     * Throws if the dependency is missing.
     *
     * ```
     * $db = $env->read(Database::class);
     * ```
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
     * ```
     * $cache = $env->get(CacheInterface::class); // null if not present
     * ```
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
     *  ```
     *  $result = $env->local(function (IEnv $local) {
     *      return 'ok';
     *  });
     *  ```
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
