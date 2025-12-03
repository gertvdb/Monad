<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use LogicException;
use Throwable;
use TypeError;
use ValueError;

final class Option
{
    private function __construct(
        private readonly bool $hasValue,
        private readonly mixed $value = null
    ) {
    }

    // ------------------------------------------------------------
    //  Constructors
    // ------------------------------------------------------------

    public static function some(mixed $value): self
    {
        return new self(true, $value);
    }

    public static function none(): self
    {
        return new self(false);
    }

    // ------------------------------------------------------------
    //  Basic state
    // ------------------------------------------------------------

    public function isSome(): bool
    {
        return $this->hasValue;
    }

    public function isNone(): bool
    {
        return !$this->hasValue;
    }

    // ------------------------------------------------------------
    //  Bind
    // ------------------------------------------------------------

    public function bind(callable $fn): self
    {
        if ($this->isNone()) {
            return $this;
        }

        try {
            $result = $fn($this->value);
            return $result instanceof self ? $result : self::none();
        } catch (Throwable $e) {
            return self::none(); // silently fail on exceptions, in an optional a wrong value is no value
        }
    }

    // ------------------------------------------------------------
    //  Side-effects
    // ------------------------------------------------------------

    public function inspect(callable $fn): self
    {
        if ($this->isSome()) {
            $fn($this->value);
        }
        return $this;
    }

    // ------------------------------------------------------------
    //  Unwrap
    // ------------------------------------------------------------

    public function unwrap(): mixed
    {
        if ($this->isNone()) {
            throw new ValueError('Cannot unwrap value from None');
        }
        return $this->value;
    }

    public function unwrapOr(mixed $default): mixed
    {
        return $this->isSome() ? $this->value : $default;
    }

    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->isSome() ? $this->value : $fn();
    }

    // ------------------------------------------------------------
    //  Transform
    // ------------------------------------------------------------

    public function okOr(Throwable $err): Result
    {
        return $this->isSome() ? Result::ok($this->value) : Result::err($err);
    }
}
