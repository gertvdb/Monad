<?php

declare(strict_types=1);

namespace GertVdb\Monad\Monads\Optional;

use GertVdb\Monad\Optional;
use ValueError;

/**
 * @template T
 * @implements Optional<T>
 */
final class None implements Optional
{
    /**
     * @template U
     * @return None<U>
     */
    public static function of(): self
    {
        return new self();
    }

    /**
     * @template U
     * @param callable(T): Optional<U> $fn
     * @return None<U>
     */
    public function bind(callable $fn): None
    {
        return $this;
    }

    /**
     * @return T
     * @throws ValueError
     */
    public function unwrap(): mixed
    {
        throw new ValueError('Value of None can not be unwrapped');
    }

    /**
     * @template U
     * @param callable(T): U $fn
     * @return None<U>
     */
    public function map(callable $fn): None
    {
        return $this;
    }
}
