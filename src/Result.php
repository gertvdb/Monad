<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Exception;
use LogicException;
use Stringable;
use Throwable;
use TypeError;

final readonly class Result implements IResult
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
        ?IEnv    $env = null,
        ?IWriter $writer = null,
    ): self {
        return new self(
            ok: true,
            valueOrError: $value,
            env: $env ?? Env::empty(),
            writer: $writer ?? Writer::empty(),
        );
    }

    public static function err(
        string|Stringable|Throwable $error,
        ?IEnv                        $env = null,
        ?IWriter                     $writer = null,
    ): self {
        $dueTo = $error instanceof Throwable ? $error : new Exception((string)$error);

        return new self(
            ok: false,
            valueOrError: $dueTo,
            env: $env ?? Env::empty(),
            writer: $writer ?? Writer::empty(),
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
    //  Transform / Failing
    // ------------------------------------------------------------

    /**
     * lift() produces a new Result::ok with the passed value and keeps env and writer.
     */
    public function lift(mixed $value): self
    {
        if ($this->isErr()) {
            return $this;
        }

        return new self(
            ok: true,
            valueOrError: $value,
            env: $this->env,
            writer: $this->writer,
        );
    }

    /**
     * fail() produces a new Result::err with the passed error and keeps env and writer.
     */
    public function fail(string|Stringable|Throwable $error): self
    {
        $dueTo = $error instanceof Throwable ? $error : new Exception((string)$error);
        return self::err(
            error: $dueTo,
            env: $this->env,
            writer: $this->writer,
        );
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
            $res = $fn($this->valueOrError);

            if ($res instanceof self) {
                // Merge writers instead of replacing
                $mergedWriter = $this->writer->merge($res->writer());
                return new self($res->isOk() ? $res->unwrap() : $res->unwrapErr(), $res->isOk(), $this->env, $mergedWriter);
            }

            // Callback returned plain value → return an error Result
            return $this->fail(new LogicException(sprintf(
                'bind() expected a Result return (T -> Result<U>), but got %s. Use map() for plain values.',
                get_debug_type($res)
            )));
        } catch (Throwable $e) {
            return $this->fail($e); // exceptions become Result::err
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
                return $this->fail(new LogicException(sprintf(
                    'bindWithEnv() failed: missing env for dependency %s',
                    get_debug_type($dependency)
                )));
            }
            $env[$dependency] = $service;
        }

        try {
            $res = $fn($this->valueOrError, $env);

            if ($res instanceof self) {
                $mergedWriter = $this->writer->merge($res->writer());
                return new self($res->isOk() ? $res->unwrap() : $res->unwrapErr(), $res->isOk(), $this->env, $mergedWriter);
            }

            // Callback returned plain value → return error Result
            return $this->fail(new LogicException(sprintf(
                'bindWithEnv() expected a Result return (T -> Result<U>), but got %s. Use mapWithEnv() for plain values.',
                get_debug_type($res)
            )));
        } catch (Throwable $e) {
            return $this->fail($e);
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
            $res = $fn($this->valueOrError);

            if ($res instanceof self) {
                // Error: map should return plain value, not Result
                return $this->fail(new LogicException(sprintf(
                    'map() expected a plain value (T -> U), but got %s. Use bind() if you want to return a Result.',
                    get_debug_type($res)
                )));
            }

            return new self($res, true, $this->env, $this->writer);

        } catch (Throwable $e) {
            return $this->fail($e);
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
                return $this->fail(new LogicException(sprintf(
                    'mapWithEnv() failed: missing env for dependency %s',
                    get_debug_type($dependency)
                )));
            }
            $env[$dependency] = $service;
        }

        try {
            $res = $fn($this->valueOrError, $env);

            if ($res instanceof self) {
                return $this->fail(new LogicException(sprintf(
                    'mapWithEnv() expected a plain value (T -> U), but got %s. Use bindWithEnv() if you want to return a Result.',
                    get_debug_type($res)
                )));
            }

            // Wrap plain value in Result, preserve writer
            return new self($res, true, $this->env, $this->writer);

        } catch (Throwable $e) {
            return $this->fail($e);
        }
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
    public function unwrapElse(callable $fn): mixed
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
                $err = new TypeError(sprintf('withEnv() expects objects as a dependency got %s', gettype($dep)));
                return $this->fail($err);
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

    public function writeTo(string $channel, mixed $value): IResult
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
}
