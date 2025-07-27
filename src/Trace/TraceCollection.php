<?php

declare(strict_types=1);

namespace GertVdb\Monad\Trace;

use Generator;

final class TraceCollection implements Traces
{
    protected array $traces = [];

    private function __construct(
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function add(Trace $trace): self
    {
        $new = clone ($this);
        $values = [...$this->traces, $trace];
        $new->traces = $values;
        return $new;
    }

    /**
     * @return Generator<Trace>
     */
    public function getIterator(): Generator
    {
        foreach ($this->traces as $trace) {
            yield $trace;
        }
    }
}
