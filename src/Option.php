<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Env\Env;
use Gertvdb\Monad\Env\IEnv;
use Gertvdb\Monad\Writer\IWriter;
use Gertvdb\Monad\Writer\Writer;
use Throwable;
use TypeError;
use ValueError;

final class Option implements IMonad, IComposedMonad
{
    private function __construct(
        private readonly bool $hasValue,
        private readonly mixed $valueOrNull,
        private IEnv     $env,
        private IWriter  $writer,
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
            env: Env::empty(),
            writer: Writer::empty(),
        );
    }

    public static function none(): self
    {
        return new self(
            hasValue: false,
            valueOrNull: null,
            env: Env::empty(),
            writer: Writer::empty(),
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
                // Merge writers instead of replacing
                $mergedWriter = $this->writer->merge($res->writer());
                return new self(
                    hasValue: $res->isSome(),
                    valueOrNull: $res->isSome() ? $res->unwrap() : null,
                    env: $this->env,
                    writer: $mergedWriter
                );
            }

            return $this->fail();
        } catch (Throwable $e) {
            return $this->fail();
        }
    }

    public function bindWithEnv(array $dependencies, callable $fn): self
    {
        if ($this->isNone()) {
            return $this; // already none.
        }

        $env = [];
        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                return $this->fail();
            }
            $env[$dependency] = $service;
        }

        try {
            try {
                $res = $fn($this->valueOrNull, $env);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                return $this->fail();
            }

            if ($res instanceof self) {
                $mergedWriter = $this->writer->merge($res->writer());
                return new self(
                    hasValue: $res->isSome(),
                    valueOrNull: $res->isSome() ? $res->unwrap() : null,
                    env: $this->env,
                    writer: $mergedWriter
                );
            }

            // Callback returned plain value → return error Result
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
                env: $this->env,
                writer: $this->writer
            );
        } catch (Throwable $e) {
            return $this->fail();
        }
    }

    public function mapWithEnv(array $dependencies, callable $fn): self
    {
        if ($this->isNone()) {
            return $this;
        }

        $env = [];
        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                return $this->fail();
            }
            $env[$dependency] = $service;
        }

        try {
            try {
                $res = $fn($this->valueOrNull, $env);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                return $this->fail();
            }

            if ($res instanceof self) {
                return $this->fail();
            }

            // Wrap plain value in Result, preserve writer
            return new self(
                hasValue: !is_null($res),
                valueOrNull: $res,
                env: $this->env,
                writer: $this->writer
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
            ? $onSome($this->valueOrNull, $this->env, $this->writer)
            : $onNone($this->valueOrNull, $this->env, $this->writer);
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

    // ------------------------------------------------------------
    //  env (immutable)
    // ------------------------------------------------------------
    public function env(): IEnv
    {
        return $this->env;
    }

    public function withEnv(object ...$dependencies): self
    {
        $env = $this->env;
        foreach ($dependencies as $dep) {
            if (!is_object($dep)) {
                return $this->fail();
            }
            $env = $env->with($dep);
        }

        return new self(
            hasValue: $this->hasValue,
            valueOrNull: $this->valueOrNull,
            env: $env,
            writer: $this->writer,
        );
    }


    // ------------------------------------------------------------
    //  Writer (immutable)
    // ------------------------------------------------------------
    public function writer(): IWriter
    {
        return $this->writer;
    }

    public function writeTo(string $channel, mixed $value): self
    {
        $writer = $this->writer->write($channel, $value);
        return new self(
            hasValue: $this->hasValue,
            valueOrNull: $this->valueOrNull,
            env: $this->env,
            writer: $writer,
        );
    }

    public function writerOutput(string $channel): array
    {
        return $this->writer->get($channel);
    }

    private function fail(): self
    {
        return new self(
            hasValue: false,
            valueOrNull: null,
            env: $this->env,
            writer: $this->writer,
        );
    }
}
