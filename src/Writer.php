<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Trace\Trace;
use Gertvdb\Monad\Trace\Traces;

/**
 * All-in Either for the most common cases.
 *
 * @template T of Writer
 */
interface Writer
{
    /**
     * @return T
     */
    public function withTrace(Trace $trace): Writer;

    public function traces(): Traces;
}
