<?php

declare(strict_types=1);

namespace GertVdb\Monad\Trace;

interface Trace
{
    public function read(): string;

    public function at(): int;
}
