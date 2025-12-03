<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Trace;

use Generator;
use Gertvdb\Monad\Writer\WriterChannel;
use LogicException;

final class Traces implements WriterChannel
{

    private function __construct(
       private array $traces = []
    ) {
    }

    public static function empty(): self
    {
        return new self();
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

    public function count(): int
    {
       return count($this->traces);
    }

    public function append(mixed $value): self
    {
        if (!$value instanceof Trace) {
            throw new LogicException(sprintf(
                'Only Trace can be appended to "%s"',
                get_class($value)
            ));
        }

        return new self([...$this->traces, $value]);
    }

    public function merge(WriterChannel $other): self
    {
        if (!$other instanceof self) {
            throw new LogicException(sprintf(
                'Only Traces can be mergedd got "%s"',
                get_class($other)
            ));
        }

        return new self([...$this->traces, ...$other->traces]);
    }

}
