<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

/**
 * Core Monad contract used by the library.
 *
 * A monad holds a value and lets you transform it safely using
 * higher-order functions like {@see IMonad::bind()} and {@see IMonad::map()}.
 *
 * Typical implementations in this package are {@see Option} and {@see Result}.
 */
interface IMonad
{
    /**
     * Monadic bind (aka flatMap): apply a function that returns another monad
     * and flatten the result.
     *
     * Use this when your callback already returns a monad instance.
     * If your callback returns a plain value, use {@see IMonad::map()} instead.
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $r = Result::ok(2)
     *        ->bind(fn(int $i) => Result::ok($i * 3))
     *        ->bind(fn(int $i) => Result::ok($i + 1));
     * ```
     *
     *  @param callable $fn function(mixed): self
     *  @return self
     */
    public function bind(callable $fn): self;

    /**
     * Functor map: transform the inner value with a pure function.
     *
     * If you need to return a monad from the callback, use {@see IMonad::bind()}.
     *
     * ```
     * use Gertvdb\Monad\Option;
     *
     * $opt = Option::some(10)->map(fn(int $i) => $i * 2);
     * ```
     *
     * @param callable $fn function(mixed): mixed
     * @return self
     */
    public function map(callable $fn): self;

    /**
     * Extract the inner value.
     *
     * For error-capable monads like {@see Result}, this may throw if the
     * instance is in an error state. Prefer safe alternatives where provided
     * (e.g. {@see Result::unwrapOr()} or {@see Option::unwrapOr()}).
     *
     * ```
     * use Gertvdb\Monad\Result;
     *
     * $value = Result::ok(10)->unwrap();
     * ```
     */
    public function unwrap(): mixed;
}
