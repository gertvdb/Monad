<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Trace;

use Generator;

final class TraceCollection implements ITraces
{
    protected array $traces = [];

    private function __construct(
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }

    public function add(ITrace $trace): ITraces
    {
        $new = clone ($this);
        $values = [...$this->traces, $trace];
        $new->traces = $values;
        return $new;
    }

    /**
     * @return Generator<ITrace>
     */
    public function getIterator(): Generator
    {
        foreach ($this->traces as $trace) {
            yield $trace;
        }
    }
}
