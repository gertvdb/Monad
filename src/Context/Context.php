<?php

declare(strict_types=1);

namespace GertVdb\Monad\Context;

interface Context
{
    public function type(): ContextType;
}
