<?php

declare(strict_types=1);

namespace GertVdb\Monad\Trace;

use IteratorAggregate;

interface Traces extends IteratorAggregate
{
    public function add(Trace $trace): Traces;
}
