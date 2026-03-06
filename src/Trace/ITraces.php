<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Trace;

use IteratorAggregate;

interface ITraces extends IteratorAggregate
{
    public function add(ITrace $trace): ITraces;
}
