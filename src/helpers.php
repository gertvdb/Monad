<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Monads\Either\Failure;
use Gertvdb\Monad\Monads\Either\Success;

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
