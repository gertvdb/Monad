<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Context;

use Generator;
use Gertvdb\Monad\Monads\Optional\None;
use Gertvdb\Monad\Monads\Optional\Some;
use Gertvdb\Monad\Optional;

final class ContextCollection implements Contexts
{
    protected array $contexts = [];

    private function __construct()
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function get(string $class): Optional
    {
        return $this->contexts[$class] ? Some::of($this->contexts[$class]) : None::of();
    }

    public function add(Context $context): ContextCollection
    {
        $new = clone ($this);
        $contexts = $this->contexts;
        $contexts[get_class($context)] = $context;
        $new->contexts = $contexts;
        return $new;
    }

    public function remove(string $class): ContextCollection
    {
        $new = clone ($this);
        $contexts = $this->contexts;
        if (isset($contexts[$class])) {
            unset($contexts[$class]);
        }
        $new->contexts = $contexts;
        return $new;
    }

    /**
     * @return Generator<Context>
     */
    public function getIterator(): Generator
    {
        foreach ($this->contexts as $context) {
            yield $context;
        }
    }


    public function filter(callable $callback): ContextCollection
    {
        $new = clone $this;
        $new->contexts = array_filter($this->contexts, $callback);

        return $new;
    }
}
