<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Trace;

interface Trace
{
    public function read(): string;

    public function at(): int;
}
