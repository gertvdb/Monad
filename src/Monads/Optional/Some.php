<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Monads\Optional;

use Gertvdb\Monad\Optional;

/**
 * @template T
 * @implements Optional<T>
 */
final class Some implements Optional
{
    /**
     * @param T $value
     */
    private function __construct(
        protected readonly mixed $value
    ) {
    }

    /**
     * @template U
     * @param U $value
     * @return Some<U>
     */
    public static function of($value): Some
    {
        return new self($value);
    }

    /**
     * @template U
     * @param callable(T): Optional<U> $fn
     * @return Some<U>|None
     */
    public function bind(callable $fn): Some|None
    {
        return $fn($this->value);
    }

    /**
     * @return T
     * @throws \Exception
     */
    public function unwrap(): mixed
    {
        return $this->value;
    }

    /**
     * @template U
     * @param callable(T): U $fn
     * @return Some<U>
     */
    public function map(callable $fn): Some
    {
        return self::of($fn($this->value));
    }
}
