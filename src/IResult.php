<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

/**
 * Result monad contract representing success (Ok) or failure (Err).
 *
 * Implementations provide safe composition and ergonomics for branching
 * computations that may fail.
 */
interface IResult extends IMonad
{
    // ------------------------------------------------------------
    //  Basic state
    // ------------------------------------------------------------

    /**
     * Whether the result represents success.
     *
     * ```
     * use Gertvdb\Monad\Option;
     *
     * $isOk = Result::ok('x')->isOk(); // true
     * ```
     */
    public function isOk(): bool;

    /**
     * Whether the result represents failure.
     *
     *  ```
     *  use Gertvdb\Monad\Option;
     *
     *  $isErr = Result::err('boom')->isErr(); // true
     *  ```
     */
    public function isErr(): bool;


    // ------------------------------------------------------------
    //  Side-effects
    // ------------------------------------------------------------

    /**
     * Inspect the successful value without changing the result.
     *
     * ```
     * use Gertvdb\Monad\Option;
     *
     * Result::ok('hello')->inspectOk(fn($v) => error_log($v));
     * ```
     *
     * @param callable $fn function(mixed): void
     * @return self
     */
    public function inspectOk(callable $fn): self;

    /**
     * Inspect the error without changing the result.
     *
     * ```
     * use Gertvdb\Monad\Option;
     *
     * Result::err('oops')->inspectErr(fn($e) => error_log($e->getMessage()));
     * ```
     *
     * @param callable $fn function(\Throwable): void
     * @return self
     *
     */
    public function inspectErr(callable $fn): self;
}
