<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

interface IMonad
{
    // ------------------------------------------------------------
    //  Bind | Map
    // ------------------------------------------------------------
    public function bind(callable $fn): self;
    public function map(callable $fn): self;

    // ------------------------------------------------------------
    //  Unwrap
    // ------------------------------------------------------------
    public function unwrap(): mixed;
}
