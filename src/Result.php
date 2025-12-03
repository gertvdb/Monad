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
     * @param Env $env (Reader, use to pass along a certain context needed ex: Locale, Dependency Injection, ...)
     * @param Writer $writer (Writer, used to store side effects, that can later be executed or listed ex: Traces, Events, ...)
     */
    private function __construct(
        private bool    $ok,
        private mixed   $valueOrError,
        private Env     $env,
        private Writer  $writer,
    ) {}

    // ------------------------------------------------------------
    //  Constructors
    // ------------------------------------------------------------
    public static function ok(
        mixed   $value,
        ?Env    $env = null,
        ?Writer $writer = null,
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
        ?Env                        $env = null,
        ?Writer                     $writer = null,
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

    public function isOk(): bool { return $this->ok; }
    public function isErr(): bool { return !$this->ok; }

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
            return $this;
        }

        try {
            $result = $fn($this->valueOrError);

            if ($result instanceof self) {
                return $result;
            }

            return $this->fail(new LogicException(sprintf(
                'bind() expected a Result return (T -> Result<U>), but got %s. If you want to return a plain value use map() instead.',
                get_debug_type($result)
            )));
        } catch (TypeError $e) {
            return $this->fail(
                new LogicException(sprintf(
                    'Type mismatch in bind: %s',
                    $e->getMessage()
                ))
            );

        } catch (Throwable $e) {
            return $this->fail($e);
        }
    }

    public function bindWithEnv(array $dependencies, callable $fn): self
    {
        if ($this->isErr()) {
            return $this;
        }

        $env = [];

        foreach ($dependencies as $dependency) {
            $context = $this->env->get($dependency);
            if (!$context) {
                return $this->fail(new LogicException(sprintf(
                    'bindWithEnv() failed: missing env for dependency %s',
                    get_debug_type($dependency)
                )));
            }
            $env[$dependency] = $context;
        }

        try {
            $result = $fn($this->valueOrError, $env);

            if ($result instanceof self) {
                return $result;
            }

            return $this->fail(new LogicException(sprintf(
                'bindWithEnv() expected a Result return (T -> Result<U>), but got %s. If you want to return a plain value use mapEnv() instead.',
                get_debug_type($result)
            )));

        } catch (TypeError $e) {
            return $this->fail(
                new LogicException(sprintf(
                    'Type mismatch in bindWithEnv: %s',
                    $e->getMessage()
                ))
            );

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
            $result = $fn($this->valueOrError);

            if ($result instanceof self) {
                return $this->fail(new LogicException(
                    'map() must return a plain value (T -> U). It cannot return a Result. '
                    .'If your function returns a Result, use bind() instead.'
                ));
            }

            return $this->lift($result);
        } catch (TypeError $e) {
            return $this->fail(
                new LogicException(sprintf(
                    'Type mismatch in Map: %s',
                    $e->getMessage()
                ))
            );
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

        $scopedEnv = Env::empty();

        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                return $this->fail(
                    new LogicException(sprintf(
                        'Missing required context: %s',
                        $dependency
                    ))
                );
            }
            $scopedEnv->with($dependency);
            $env[$dependency] = $service;
        }

        try {
            $result = $fn($this->valueOrError, $env);

            if ($result instanceof self) {
                return $this->fail(new LogicException(
                    'mapWithEnv() must return a plain value (T, env -> U). It cannot return a Result. '
                    .'If your function returns a Result, use bindWithEnv() instead.'
                ));
            }

            return $this->lift($result);

        } catch (TypeError $e) {
            return $this->fail(
                new LogicException(sprintf(
                    'Type mismatch in mapWithEnv: %s',
                    $e->getMessage()
                ))
            );

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
            ? $onOk($this->valueOrError)
            : $onErr($this->valueOrError);
    }

    public function foldWithEnv(callable $onOk, callable $onErr): mixed
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
    public function value(): mixed {
        return $this->isOk() ? $this->valueOrError : null;
    }

    /**
     * @return Throwable|null The Throwable error or null.
     */
    public function error(): ?Throwable {
        return !$this->isOk() ? $this->valueOrError : null;
    }

    // ------------------------------------------------------------
    //  env (immutable)
    // ------------------------------------------------------------
    public function env(): Env
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
    public function writer(): Writer
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
