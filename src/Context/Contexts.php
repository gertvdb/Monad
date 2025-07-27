<?php

declare(strict_types=1);

namespace GertVdb\Monad\Context;

use IteratorAggregate;
use GertVdb\Monad\Optional;

interface Contexts extends IteratorAggregate
{
    public function get(string $class): Optional;

    public function add(Context $context): Contexts;

    public function remove(string $class): Contexts;
}
