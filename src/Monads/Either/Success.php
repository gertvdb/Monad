<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Monads\Either;

use Gertvdb\Monad\Context\Context;
use Gertvdb\Monad\Context\ContextCollection;
use Gertvdb\Monad\Context\Contexts;
use Gertvdb\Monad\Either;
use Gertvdb\Monad\Fault;
use Gertvdb\Monad\Optional;
use Gertvdb\Monad\Trace\Trace;
use Gertvdb\Monad\Trace\TraceCollection;
use Gertvdb\Monad\Trace\Traces;
use Throwable;

/**
 * @template T
 * @implements Either<T>
 */
final class Success implements Either
{
    /**
     * @param T $value
     */
    private function __construct(
        protected readonly mixed $value,
        protected Traces $traces,
        protected Contexts $contexts
    ) {
    }

    /**
     * @template U
     * @param U $value
     * @param Traces|null $traces
     * @param Contexts|null $contexts
     * @return Success<U>
     */
    public static function of(mixed $value, ?Traces $traces = null, ?Contexts $contexts = null): Success
    {
        return new self(
            value: $value,
            traces: $traces ?? TraceCollection::empty(),
            contexts: $contexts ?? ContextCollection::empty()
        );
    }

    /**
     * @template U
     * @param U $value
     * @return Success<U>
     */
    public function lift($value): Success
    {
        return new self(
            value: $value,
            traces: $this->traces,
            contexts: $this->contexts
        );
    }

    public function fail(Fault $fault, Trace|null $trace = null): Failure
    {
        // Keep only persistent contexts
        $persistentContexts = $this->contexts->filter(function (Context $context) {
            return $context->type()->isPersistent();
        });

        return Failure::dueTo(
            message: $fault->message,
            code: $fault->code,
            previous: $fault->previous,
            trace: $trace,
            traces: $this->traces,
            contexts: $persistentContexts
        );
    }

    /**
     * @template T
     * @template U
     * @param callable(T): Success<U>|Failure $fn
     * @return Success<U>|Failure
     */
    public function bind(callable $fn): Success|Failure
    {
        return $fn($this->value);
    }

    /**
     * @template U
     * @param callable(T): U $fn
     * @return Success<U>
     */
    public function map(callable $fn): Success
    {
        return self::of(
            value: $fn($this->value),
            traces: $this->traces,
            contexts: $this->contexts
        );
    }

    /**
     * @return T
     */
    public function unwrap(): mixed
    {
        return $this->value;
    }

    public function unwrapError(): Throwable
    {
        throw new \LogicException('Cannot unwrap error from Success');
    }

    public function withTrace(Trace $trace): Success
    {
        return $this->withTraces($this->traces->add($trace));
    }

    public function traces(): Traces
    {
        return $this->traces;
    }

    public function withContext(Context $context): Success
    {
        return $this->withContexts($this->contexts->add($context));
    }

    public function context(string $class): Optional
    {
        return $this->contexts->get($class);
    }

    public function clearContext(string $class): Success
    {
        return $this->withContexts($this->contexts->remove($class));
    }

    private function withTraces(Traces $traces): self
    {
        $new = clone $this;
        $new->traces = $traces;
        return $new;
    }

    private function withContexts(Contexts $contexts): self
    {
        $new = clone $this;
        $new->contexts = $contexts;
        return $new;
    }
}
