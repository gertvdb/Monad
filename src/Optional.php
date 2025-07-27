<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Monads\Optional\None;
use Gertvdb\Monad\Monads\Optional\Some;

/**
 * Maybe Monad (Optional)
 *
 * Either monad represents either a Some or a None.
 * In this interface, we restrict the common monad to these
 * two types.
 *
 * @template T
 * @extends MonadCommon<T>
 */
interface Optional extends MonadCommon
{
    /**
     * @template U
     * @param callable(T): Optional<U> $fn
     * @return Some<U>|None
     */
    public function bind(callable $fn): Some|None;

    /**
     * @return T
     */
    public function unwrap(): mixed;

    /**
     * @template U
     * @param callable(T): U $fn
     * @return Some<U>|None
     */
    public function map(callable $fn): Some|None;
}
