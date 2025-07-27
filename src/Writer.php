<?php

declare(strict_types=1);

namespace GertVdb\Monad;

use GertVdb\Monad\Trace\Trace;
use GertVdb\Monad\Trace\Traces;

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
