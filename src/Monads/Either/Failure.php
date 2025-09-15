<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Monads\Either;

use Exception;
use Gertvdb\Monad\Context\Context;
use Gertvdb\Monad\Context\ContextCollection;
use Gertvdb\Monad\Context\Contexts;
use Gertvdb\Monad\Either;
use Gertvdb\Monad\FailureError;
use Gertvdb\Monad\Fault;
use Gertvdb\Monad\Optional;
use Gertvdb\Monad\Trace\Trace;
use Gertvdb\Monad\Trace\TraceCollection;
use Gertvdb\Monad\Trace\TraceCommon;
use Gertvdb\Monad\Trace\Traces;
use Throwable;
use ValueError;

final class Failure extends FailureError implements Either
{
    private function __construct(
        protected readonly Throwable $exception,
        protected Traces $traces,
        protected Contexts $contexts
    ) {
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
    }

    public static function dueTo(
        string $message,
        int $code = 0,
        Throwable|null $previous = null,
        Trace|null $trace = null,
        Traces|null $traces = null,
        Contexts|null $contexts = null,
    ): Failure {
        $exception = new Exception(
            message: $message,
            code: $code,
            previous: $previous
        );

        // Add Failure traces as last item.
        $traces = $traces ?? TraceCollection::empty();
        if ($trace) {
            $traces = $traces->add($trace);
        } else {
            // Fallback to a common trace of the message when no specfic trace was provided.
            $traces = $traces->add(TraceCommon::from($message, time()));
        }

        return new Failure(
            exception: $exception,
            traces: $traces,
            contexts: $contexts ?? ContextCollection::empty()
        );
    }

    public function bind(callable $fn): Failure
    {
        return $this;
    }

    public function bindWithContext(array $requiredContexts, callable $fn): Failure {
        return $this;
    }

    public function unwrap(): mixed
    {
        throw new ValueError('Value of Failure can not be unwrapped', 0, $this->exception);
    }

    public function unwrapError(): Throwable
    {
        return $this->exception;
    }

    public function map(callable $fn): Failure
    {
        return $this;
    }

    public function mapWithContext(array $requiredContexts, callable $fn): Failure {
        return $this;
    }

    public function withTrace(Trace $trace): Failure
    {
        // Recreation needed since we cannot clone Throwable.
        return self::dueTo(
            $this->message,
            $this->code,
            $this->exception->getPrevious(),
            $trace,
            $this->traces,
            $this->contexts
        );
    }

    public function traces(): Traces
    {
        return $this->traces;
    }

    public function withContext(Context $context): Failure
    {
        // Recreation needed since we cannot clone Throwable.
        $contexts = $this->contexts->add($context);
        return self::dueTo(
            $this->message,
            $this->code,
            $this->exception->getPrevious(),
            null,
            $this->traces,
            $contexts
        );
    }

    public function clearContext(string $class): Failure
    {
        // Recreation needed since we cannot clone Throwable.
        $contexts = $this->contexts->remove($class);
        return self::dueTo(
            $this->message,
            $this->code,
            $this->exception->getPrevious(),
            null,
            $this->traces,
            $contexts
        );
    }

    public function context(string $class): Optional
    {
        return $this->contexts->get($class);
    }

    public function lift($value): Failure
    {
        return $this;
    }

    public function fail(
        Fault $fault,
        Trace|null $trace = null
    ): Failure {
        return $this;
    }
}
