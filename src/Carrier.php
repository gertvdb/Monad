<?php

declare(strict_types=1);

namespace GertVdb\Monad;

/**
 * Carrier monad, an interface for a monad that will replicate
 * itself with only a value change. This is used to keep context
 * traces over transformations.
 *
 * @template T
 * @extends MonadCommon<T>
 */
interface Carrier extends MonadCommon
{
    /**
     * @template U
     * @param U $value
     * @return Carrier<U>
     */
    public function lift(mixed $value): Carrier;
}
