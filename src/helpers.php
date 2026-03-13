<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Trace\ITrace;

/**
 * Add pre-instantiated services to Result's Env.
 */
function service(Result $result, object $service): Result
{
    return $result->withServices($service);
}

/**
 * Add pre-instantiated services to Result's Env.
 */
function services(Result $result, object ...$services): Result
{
    return $result->withServices(...$services);
}

/**
 * Add factories (lazy services) to Result's Env.
 *
* @param Result $result
* @param string $class
* @param callable|string $factory
* @return Result
*/
function factory(Result $result, string $class, callable|string $factory): Result
{
    return $result->withFactory($class, $factory);
}

/**
 * Add a parameter (value) to the Env.
 *
 * Useful for passing config values or constants into downstream factories.
 */
function param(Result $result, string $key, string|int|float|bool|array $value): Result
{
    return $result->withParam($key, $value);
}

/**
 * Add a tag to the Env.
 *
 * Tags can be used to retrieve multiple services by category.
 */
function tag(Result $result, string $tag, object $service): Result
{
    return $result->withTag($tag, $service);
}

/**
 * Register an interface or abstract class alias to a concrete implementation.
 */
function alias(Result $result, string $alias, object|string $implementation): Result
{
    return $result->withAlias($alias, $implementation);
}

/**
 * Add a Trace object to the Result.
 */
function trace(Result $result, ITrace $trace): Result
{
    return $result->withTrace($trace);
}

function pipe(mixed $arg, callable ...$fns): mixed
{
    foreach ($fns as $fn) {
        $arg = $fn($arg);
    }
    return $arg;
}
