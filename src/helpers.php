<?php

declare(strict_types=1);

namespace GertVdb\Monad;

use InvalidArgumentException;
use RuntimeException;
use GertVdb\Monad\Monads\Either\Failure;
use GertVdb\Monad\Monads\Either\Success;
use Throwable;

/**
 * Check if the given Either is a Success.
 *
 * @param Either $either
 * @return bool
 */
function isSuccess(Either $either): bool
{
    return $either instanceof Success;
}

/**
 * Check if the given Either is a Failure.
 *
 * @param Either $either
 * @return bool
 */
function isFailure(Either $either): bool
{
    return $either instanceof Failure;
}

/**
 * @template TSuccess
 * @template TDefault
 * @param Either<TSuccess, mixed> $either
 * @param TDefault $default
 * @return TSuccess|TDefault
 */
function getOrElse(Either $either, mixed $default): mixed
{
    return isSuccess($either) ? $either->unwrap() : $default;
}

function unwrapAs(Either $either, string $expectedClass): object
{
    if (isFailure($either)) {
        throw new RuntimeException("Cannot unwrap failure");
    }

    $value = $either->unwrap();

    if (!($value instanceof $expectedClass)) {
        throw new InvalidArgumentException("Expected instance of $expectedClass, got " . get_debug_type($value));
    }

    return $value;
}

/**
 * @template TSuccess
 * @template TFailure of Throwable
 * @template TResult
 * @param Either<TSuccess, TFailure> $either
 * @param callable(TFailure): TResult $onFailure
 * @param callable(TSuccess): TResult $onSuccess
 * @return TResult
 */
function fold(Either $either, callable $onFailure, callable $onSuccess): mixed
{
    return isSuccess($either)
        ? $onSuccess($either->unwrap())
        : $onFailure($either->unwrapError());
}

/**
 * @template TSuccess
 * @template TFailure of Throwable
 * @template TResult
 * @param Either<TSuccess, TFailure> $either
 * @param callable(TFailure): TResult $onFailure
 * @param callable(TSuccess): TResult $onSuccess
 * @return TResult
 */
function doMatch(Either $either, callable $onSuccess, callable $onFailure): mixed
{
    return fold($either, $onFailure, $onSuccess);
}

/**
 * @template TSuccess
 * @template TFailure of Throwable
 * @param Either<TSuccess, TFailure> $either
 * @param mixed $value
 * @return bool
 */
function contains(Either $either, mixed $value): bool
{
    return isSuccess($either) && $either->unwrap() === $value;
}

function recover(Either $either, callable $fn): Either
{
    if (isSuccess($either)) {
        return $either;
    }

    return $either->lift($fn($either->unwrapError()));
}

function ensure(Either $either, callable $predicate, Fault $fault): Either
{
    if (isFailure($either)) {
        return $either;
    }

    return $predicate($either->unwrap())
        ? $either
        : $either->fail($fault);
}

function sequence(array $eithers): Either
{
    $results = [];
    $base = null;

    foreach ($eithers as $either) {
        if (isFailure($either)) {
            return $either;
        }

        $base ??= $either;
        $results[] = $either->unwrap();
    }

    return $base->lift($results);
}
