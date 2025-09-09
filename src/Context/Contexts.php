<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Context;

use Gertvdb\Monad\Optional;
use IteratorAggregate;

interface Contexts extends IteratorAggregate
{
    public function get(string $class): Optional;

    public function add(Context $context): Contexts;

    public function remove(string $class): Contexts;
}
