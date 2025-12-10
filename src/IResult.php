<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Env\IEnv;
use Gertvdb\Monad\Writer\IWriter;

interface IResult extends IMonad
{
    // ------------------------------------------------------------
    //  Basic state
    // ------------------------------------------------------------
    public function isOk(): bool;
    public function isErr(): bool;

    // ------------------------------------------------------------
    //  Side-effects
    // ------------------------------------------------------------
    public function inspectOk(callable $fn): self;
    public function inspectErr(callable $fn): self;
}
