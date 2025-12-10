<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Exception;
use Gertvdb\Monad\Env\Env;
use Gertvdb\Monad\Env\IEnv;
use Gertvdb\Monad\Writer\IWriter;
use Gertvdb\Monad\Writer\Writer;
use LogicException;
use Stringable;
use Throwable;
use TypeError;

final readonly class Result implements IResult, IComposedMonad
{
    /**
     * @param bool $ok
     * @param mixed $valueOrError
     * @param IEnv $env (Reader, use to pass along a certain context needed ex: Locale, Dependency Injection, ...)
     * @param IWriter $writer (Writer, used to store side effects, that can later be executed or listed ex: Traces, Events, ...)
     */
    private function __construct(
        private bool    $ok,
        private mixed   $valueOrError,
        private IEnv     $env,
        private IWriter  $writer,
    ) {
    }

    // ------------------------------------------------------------
    //  Constructors
    // ------------------------------------------------------------
    public static function ok(
        mixed   $value,
    ): self {
        return new self(
            ok: true,
            valueOrError: $value,
            env: Env::empty(),
            writer: Writer::empty(),
        );
    }

    public static function err(
        string|Stringable|Throwable $error,
    ): self {
        $dueTo = $error instanceof Throwable ? $error : new Exception((string)$error);

        return new self(
            ok: false,
            valueOrError: $dueTo,
            env: Env::empty(),
            writer: Writer::empty(),
        );
    }

    // ------------------------------------------------------------
    //  Basic state
    // ------------------------------------------------------------

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function isErr(): bool
    {
        return !$this->ok;
    }

    // ------------------------------------------------------------
    //  bind | bindWithEnv
    //  Needs to return a Result inside the bind.
    //  Change value with with() to keep context.
    // ------------------------------------------------------------

    public function bind(callable $fn): self
    {
        if ($this->isErr()) {
            return $this; // already an error Result
        }

        try {
            try {
                $res = $fn($this->valueOrError);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                return $this->fail(
                    error: new LogicException(sprintf(
                        'bind() type error in callback: %s',
                        $e->getMessage()
                    ))
                );
            }

            if ($res instanceof self) {
                // Merge writers instead of replacing
                $mergedWriter = $this->writer->merge($res->writer());
                return new self(
                    ok: $res->isOk(),
                    valueOrError: $res->isOk() ? $res->unwrap() : $res->unwrapErr(),
                    env: $this->env,
                    writer: $mergedWriter
                );
            }

            // Callback returned plain value → return an error Result
            return $this->fail(
                error: new LogicException(sprintf(
                    'bind() expected a Result return (T -> Result<U>), but got %s. Use map() for plain values.',
                    get_debug_type($res)
                ))
            );
        } catch (Throwable $e) {
            return $this->fail(
                error: $e
            );
        }
    }

    public function bindWithEnv(array $dependencies, callable $fn): self
    {
        if ($this->isErr()) {
            return $this; // already an error
        }

        $env = [];
        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                return $this->fail(
                    error: new LogicException(sprintf(
                        'bindWithEnv() failed: missing env for dependency %s',
                        get_debug_type($dependency)
                    ))
                );
            }
            $env[$dependency] = $service;
        }

        try {
            try {
                $res = $fn($this->valueOrError, $env);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                return $this->fail(
                    error: new LogicException(sprintf(
                        'bindWithEnv() type error in callback: %s',
                        $e->getMessage()
                    ))
                );
            }

            if ($res instanceof self) {
                $mergedWriter = $this->writer->merge($res->writer());
                return new self(
                    ok: $res->isOk(),
                    valueOrError: $res->isOk() ? $res->unwrap() : $res->unwrapErr(),
                    env: $this->env,
                    writer: $mergedWriter
                );
            }

            // Callback returned plain value → return error Result
            return $this->fail(
                error: new LogicException(sprintf(
                    'bindWithEnv() expected a Result return (T -> Result<U>), but got %s. Use mapWithEnv() for plain values.',
                    get_debug_type($res)
                ))
            );
        } catch (Throwable $e) {
            return $this->fail(
                error: $e
            );
        }
    }

    // ------------------------------------------------------------
    //  map | mapWithEnv
    //  Needs to return the modified value inside the bind.
    // ------------------------------------------------------------

    public function map(callable $fn): self
    {
        if ($this->isErr()) {
            return $this;
        }

        try {
            try {
                $res = $fn($this->valueOrError);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                return $this->fail(
                    error: new LogicException(sprintf(
                        'map() type error in callback: %s',
                        $e->getMessage()
                    ))
                );
            }

            if ($res instanceof self) {
                // Error: map should return plain value, not Result
                return $this->fail(
                    error: new LogicException(sprintf(
                        'map() expected a plain value (T -> U), but got %s. Use bind() if you want to return a Result.',
                        get_debug_type($res)
                    ))
                );
            }

            return new self(
                ok: true,
                valueOrError: $res,
                env: $this->env,
                writer: $this->writer
            );
        } catch (Throwable $e) {
            return $this->fail(
                error: $e
            );
        }
    }

    public function mapWithEnv(array $dependencies, callable $fn): self
    {
        if ($this->isErr()) {
            return $this;
        }

        $env = [];
        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                return $this->fail(
                    error: new LogicException(sprintf(
                        'mapWithEnv() failed: missing env for dependency %s',
                        get_debug_type($dependency)
                    ))
                );
            }
            $env[$dependency] = $service;
        }

        try {
            try {
                $res = $fn($this->valueOrError, $env);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                return $this->fail(
                    error: new LogicException(sprintf(
                        'mapWithEnv() type error in callback: %s',
                        $e->getMessage()
                    ))
                );
            }

            if ($res instanceof self) {
                return $this->fail(
                    error: new LogicException(sprintf(
                        'mapWithEnv() expected a plain value (T -> U), but got %s. Use bindWithEnv() if you want to return a Result.',
                        get_debug_type($res)
                    ))
                );
            }

            // Wrap plain value in Result, preserve writer
            return new self(
                ok: true,
                valueOrError: $res,
                env: $this->env,
                writer: $this->writer
            );
        } catch (Throwable $e) {
            return $this->fail(
                error: $e
            );
        }
    }


    // ------------------------------------------------------------
    //  apply |
    // Takes a Result<callable> and applies it to this Result<T>
    // ------------------------------------------------------------

    public function apply(self $fnResult): self
    {
        // If either side is an Err → short-circuit with the first error
        if ($this->isErr()) {
            return $this;
        }
        if ($fnResult->isErr()) {
            return $fnResult;
        }

        // Extract function (ensure it's callable)
        $fn = $fnResult->unwrap();
        if (!is_callable($fn)) {
            return $this->fail(
                new LogicException(sprintf(
                    'apply() expects Result<callable>, got %s',
                    get_debug_type($fn)
                ))
            );
        }

        // Apply function safely
        try {
            try {
                $newValue = $fn($this->valueOrError);
            } catch (TypeError $e) {
                return $this->fail(
                    new LogicException(sprintf(
                        'apply() callable type error: %s',
                        $e->getMessage()
                    ))
                );
            }
        } catch (Throwable $e) {
            return $this->fail($e);
        }

        // Function must return plain value (not another Result)
        if ($newValue instanceof self) {
            return $this->fail(
                new LogicException(
                    'apply() callable must return plain value, not a Result. Use bind() for nested results.'
                )
            );
        }

        // Merge writers just like in bind()
        $mergedWriter = $this->writer->merge($fnResult->writer());

        return new self(
            ok: true,
            valueOrError: $newValue,
            env: $this->env,     // preserve env
            writer: $mergedWriter
        );
    }

    // ------------------------------------------------------------
    //  applyWithEnv
    //  Applicative version that passes Env to the function.
    //  Takes Result<callable($value, array $env): U>
    //  and applies it to this Result<T>
    //  producing Result<U>.
    //
    //  Rules:
    //  - If either this or the function is Err → return Err
    //  - Function receives ($value, $envArray)
    //  - Function must return a plain value, not a Result
    //  - Env is passed but never changed
    //  - Writers merge (same as bind)
    // ------------------------------------------------------------
    public function applyWithEnv(self $fnResult, array $dependencies = []): self
    {
        // Short-circuit on existing error
        if ($this->isErr()) {
            return $this;
        }

        if ($fnResult->isErr()) {
            return $fnResult;
        }

        // Extract the callable from Result<callable>
        $fn = $fnResult->unwrap();
        if (!is_callable($fn)) {
            return $this->fail(
                new LogicException(sprintf(
                    'applyWithEnv() expects Result<callable>, got %s',
                    get_debug_type($fn)
                ))
            );
        }

        // Build env array for callback
        $env = [];
        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                return $this->fail(
                    new LogicException(sprintf(
                        'applyWithEnv() failed: missing env for dependency %s',
                        get_debug_type($dependency)
                    ))
                );
            }
            $env[$dependency] = $service;
        }

        // Apply function($value, $env)
        try {
            try {
                $newValue = $fn($this->valueOrError, $env);
            } catch (TypeError $e) {
                return $this->fail(
                    new LogicException(sprintf(
                        'applyWithEnv() callable type error: %s',
                        $e->getMessage()
                    ))
                );
            }
        } catch (Throwable $e) {
            return $this->fail($e);
        }

        // Function must NOT return a Result
        if ($newValue instanceof self) {
            return $this->fail(
                new LogicException(
                    'applyWithEnv() callable must return plain value, not a Result. Use bindWithEnv() if you need to return a Result.'
                )
            );
        }

        // Merge writers (applicative semantics + your design)
        $mergedWriter = $this->writer->merge($fnResult->writer());

        // Return new Result with merged writer and same env
        return new self(
            ok: true,
            valueOrError: $newValue,
            env: $this->env,
            writer: $mergedWriter
        );
    }


    // ------------------------------------------------------------
    //  Side-effect without changing a pipeline
    //  (ex: $result->inspectOk(fn($value) => var_dump($value));
    // ------------------------------------------------------------

    public function inspectOk(callable $fn): self
    {
        if ($this->isOk()) {
            $fn($this->valueOrError); // side-effect with ok
        }
        return $this;
    }

    public function inspectErr(callable $fn): self
    {
        if ($this->isErr()) {
            $fn($this->valueOrError); // side-effect with error
        }
        return $this;
    }


    // ------------------------------------------------------------
    //  Unwrap | Fold
    // ------------------------------------------------------------

    public function fold(callable $onOk, callable $onErr): mixed
    {
        return $this->isOk()
            ? $onOk($this->valueOrError, $this->env, $this->writer)
            : $onErr($this->valueOrError, $this->env, $this->writer);
    }

    /**
     * @return mixed The plain value
     * @throws Throwable If called on an Err result
     */
    public function unwrap(): mixed
    {
        if ($this->isErr()) {
            throw $this->valueOrError;
        }
        return $this->valueOrError;
    }

    /**
     * @return Throwable  The error if this is Err(...)
     * @throws LogicException If called on an Ok result
     */
    public function unwrapErr(): Throwable
    {
        if (!$this->isErr()) {
            throw new LogicException('You cannot unwrap error of a ok result');
        }
        return $this->valueOrError;
    }

    /**
     * @param mixed $default The default value.
     * @return mixed The plain value or the default.
     */
    public function unwrapOr(mixed $default): mixed
    {
        return $this->isOk() ? $this->valueOrError : $default;
    }

    /**
     * @param callable $fn The function to call as default.
     * @return mixed The plain value or the function to call as default.
     */
    public function unwrapOrElse(callable $fn): mixed
    {
        return $this->isOk() ? $this->valueOrError : $fn();
    }

    /**
     * @return mixed|null The plain value or null.
     */
    public function value(): mixed
    {
        return $this->isOk() ? $this->valueOrError : null;
    }

    /**
     * @return Throwable|null The Throwable error or null.
     */
    public function error(): ?Throwable
    {
        return !$this->isOk() ? $this->valueOrError : null;
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
                return $this->fail(
                    error: new TypeError(sprintf('withEnv() expects objects as a dependency got %s', gettype($dep)))
                );
            }
            $env = $env->with($dep);
        }

        return new self(
            ok: $this->ok,
            valueOrError: $this->valueOrError,
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

    public function writeTo(string $channel, mixed $value): Result
    {
        $writer = $this->writer->write($channel, $value);
        return new self(
            ok: $this->ok,
            valueOrError: $this->valueOrError,
            env: $this->env,
            writer: $writer,
        );
    }

    public function writerOutput(string $channel): array
    {
        return $this->writer->get($channel);
    }

    private function fail(Throwable $error): self
    {
        return new self(
            ok: false,
            valueOrError: $error,
            env: $this->env,
            writer: $this->writer,
        );
    }
}
