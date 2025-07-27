<?php

declare(strict_types=1);

namespace GertVdb\Monad;

/**
 * An interface for common monad functionalities.
 *
 * @template T
 */
interface MonadCommon
{
    /**
     * @template U
     * @param callable(T): MonadCommon<U> $fn
     * @return MonadCommon<U>
     */
    public function bind(callable $fn): MonadCommon;

    /**
     * @return T
     */
    public function unwrap(): mixed;

    /**
     * @template U
     * @param callable(T): U $fn
     * @return MonadCommon<U>
     */
    public function map(callable $fn): MonadCommon;
}
