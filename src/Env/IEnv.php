<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Env;

use Psr\Container\ContainerInterface;

/**
 * Immutable environment (Reader) used to pass contextual dependencies.
 *
 * You can enrich it with services and read them by class-string. Implementations
 * in this package are simple and do not require a full DI container.
 *
 * @example Basic usage
 * ```php
 * use Gertvdb\Monad\Env\Env;
 * use Psr\Log\LoggerInterface;
 *
 * $env = Env::empty()
 *     ->with(new class implements LoggerInterface {}); // provide a logger
 *
 * $logger = $env->read(LoggerInterface::class);
 * ```
 */
interface IEnv
{
    /**
     * Return a new Env with a dependency added or replaced.
     *
     * @example
     * ```php
     * $env = $env->with(new Translator('en_US'));
     * ```
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
     *
     * @example
     * ```php
     * $db = $env->read(Database::class);
     * ```
     */
    public function read(string $class): object;

    /**
     * Optionally read a dependency by class.
     *
     * @template T
     * @param class-string<T> $class
     * @return T|null
     *
     * @example
     * ```php
     * $cache = $env->get(CacheInterface::class); // null if not present
     * ```
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
     *
     * @example
     * ```php
     * $result = $env->local(function (IEnv $local) {
     *     return 'ok';
     * });
     * ```
     */
    public function local(callable $fn): mixed;

    /**
     * Return all dependencies as an array (optional, mostly for debugging)
     *
     * @return array<class-string, object>
     */
    public function all(): array;
}
