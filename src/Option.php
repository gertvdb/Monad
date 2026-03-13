<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Throwable;
use TypeError;
use ValueError;

/**
 * Optional value monad similar to Rust's Option.
 *
 * Represents either Some(value) or None. Provides safe composition to avoid
 * null checks scattered across the codebase.
 *
 * @example Basic usage
 * ```php
 * use Gertvdb\Monad\Option;
 *
 * $userName = Option::some('Alice')
 *     ->map(fn(string $s) => strtoupper($s))
 *     ->unwrap(); // 'ALICE'
 *
 * $none = Option::none()->map(fn($v) => 1); // still None
 * ```
 */
final class Option implements IMonad
{
    private function __construct(
        private readonly bool $hasValue,
        private readonly mixed $valueOrNull
    ) {
    }

    // ------------------------------------------------------------
    //  Constructors
    // ------------------------------------------------------------
    public static function some(
        mixed $value,
    ): self {
        return new self(
            hasValue: true,
            valueOrNull: $value,
        );
    }

    public static function none(): self
    {
        return new self(
            hasValue: false,
            valueOrNull: null,
        );
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
    //  bind | bindWithEnv
    //  Needs to return a Result inside the bind.
    //  Change value with with() to keep context.
    // ------------------------------------------------------------

    public function bind(callable $fn): self
    {
        if ($this->isNone()) {
            return $this;
        }

        try {
            try {
                $res = $fn($this->valueOrNull);
            } catch (TypeError $e) {
                // silently fail on exceptions, in an optional a wrong value is no value
                return $this->fail();
            }

            if ($res instanceof self) {
                return new self(
                    hasValue: $res->isSome(),
                    valueOrNull: $res->isSome() ? $res->unwrap() : null,
                );
            }

            return $this->fail();
        } catch (Throwable $e) {
            return $this->fail();
        }
    }

    // ------------------------------------------------------------
    //  map | mapWithEnv
    //  Needs to return the modified value inside the bind.
    // ------------------------------------------------------------

    public function map(callable $fn): self
    {
        if ($this->isNone()) {
            return $this;
        }

        try {
            try {
                $res = $fn($this->valueOrNull);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                return $this->fail();
            }

            if ($res instanceof self) {
                // Error: map should return plain value or null, not Option
                return $this->fail();
            }

            return new self(
                hasValue: !is_null($res),
                valueOrNull: $res,
            );
        } catch (Throwable $e) {
            return $this->fail();
        }
    }

    // ------------------------------------------------------------
    //  Side-effects
    // ------------------------------------------------------------

    public function inspectSome(callable $fn): self
    {
        if ($this->isSome()) {
            $fn($this->valueOrNull); // side-effect with some
        }
        return $this;
    }

    // ------------------------------------------------------------
    //  Unwrap | Fold
    // ------------------------------------------------------------

    public function fold(callable $onSome, callable $onNone): mixed
    {
        return $this->isSome()
            ? $onSome($this->valueOrNull)
            : $onNone($this->valueOrNull);
    }

    public function unwrap(): mixed
    {
        if ($this->isNone()) {
            throw new ValueError('Cannot unwrap value from None');
        }
        return $this->valueOrNull;
    }

    public function unwrapOr(mixed $default): mixed
    {
        return $this->isSome() ? $this->valueOrNull : $default;
    }

    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->isSome() ? $this->valueOrNull : $fn();
    }

    /**
     * @return mixed|null The plain value or null.
     */
    public function value(): mixed
    {
        return $this->isSome() ? $this->valueOrNull : null;
    }

    private function fail(): self
    {
        return new self(
            hasValue: false,
            valueOrNull: null,
        );
    }
}
