<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Context;

interface Context
{
    public function type(): ContextType;
}
