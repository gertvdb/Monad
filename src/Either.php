<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Monads\Either\Failure;
use Gertvdb\Monad\Monads\Either\Success;
use Gertvdb\Monad\Trace\Trace;
use Throwable;


/**
 * @template T
 * @extends Writer<Either<T>>
 * @extends Reader<Either<T>>
 * @extends Carrier<T>
 */
interface Either extends Writer, Reader, Carrier
{
    /**
     * @template U
     * @param callable(T): Either<U> $fn
     * @return Success<U>|Failure
     */
    public function bind(callable $fn): Success|Failure;

    /**
     * @return T
     */
    public function unwrap(): mixed;

    /**
     * @return Throwable
     */
    public function unwrapError(): Throwable;

    /**
     * @template U
     * @param callable(T): U $fn
     * @return Success<U>|Failure
     */
    public function map(callable $fn): Success|Failure;

    /**
     * @template U
     * @param U $value
     * @return Success<U>|Failure
     */
    public function lift(mixed $value): Success|Failure;

    /**
     * @return Failure
     */
    public function fail(Fault $fault, Trace|null $trace = null): Failure;
}
