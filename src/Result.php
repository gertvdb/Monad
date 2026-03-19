<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Closure;
use Exception;
use Gertvdb\Monad\Env\Env;
use Gertvdb\Monad\Env\IEnv;
use Gertvdb\Monad\Trace\ITrace;
use Gertvdb\Monad\Trace\ITraces;
use Gertvdb\Monad\Trace\TraceCollection;
use Gertvdb\Monad\Writer\IWriter;
use Gertvdb\Monad\Writer\Writer;
use LogicException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Stringable;
use Throwable;
use TypeError;

/**
 * Result monad representing either success (Ok) with a value or failure (Err) with a throwable.
 *
 * Provides rich composition primitives (bind/map), environment-aware operations, and a writer
 * to accumulate side-effects such as traces or audit logs.
 */
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
    //  bind
    //  Needs to return a Result inside the bind.
    //  Change value with with() to keep context.
    // ------------------------------------------------------------
    public function bind(callable $fn): self
    {
        if ($this->isErr()) {
            return $this;
        }

        $resolved = $this->resolveCallback($fn, [$this->valueOrError]);
        if ($resolved instanceof self) {
            return $resolved; // early fail
        }

        try {
            $res = $fn(...$resolved);
        } catch (TypeError $e) {
            return $this->fail(
                error: new LogicException("bind() type error: {$e->getMessage()}")
            );
        }

        if (!($res instanceof self)) {
            return $this->fail(
                error: new LogicException(
                    "bind() expected Result return, got plain value. Use map() instead."
                )
            );
        }

        return new self(
            ok: $res->isOk(),
            valueOrError: $res->isOk() ? $res->value() : $res->unwrapErr(),
            env: $this->env->merge($res->env()),
            writer: $this->writer->merge($res->writer())
        );
    }

    public function bindFirst(callable $fn): self
    {
        if ($this->isErr()) {
            return $this; // short-circuit if already error
        }

        try {
            $inner = $fn($this); // call failable side-effect with current Result
        } catch (Throwable $e) {
            return $this->fail($e);
        }

        if (!($inner instanceof self)) {
            return $this->fail(new LogicException(
                'bindFirst() expects callback to return a Result.'
            ));
        }

        // propagate failure if inner failed
        if ($inner->isErr()) {
            return new self(
                ok: false,
                valueOrError: $inner->unwrapErr(),
                env: $this->env,
                writer: $this->writer->merge($inner->writer())
            );
        }

        // success: keep original value, merge writer
        return new self(
            ok: true,
            valueOrError: $this->valueOrError, // preserve original
            env: $this->env,
            writer: $this->writer->merge($inner->writer())
        );
    }

    // Helpers
    public function try(
        callable $fn,
        string|Stringable|Throwable|callable|null $mapError = null
    ): self
    {
        if ($this->isErr()) {
            return $this;
        }

        $resolved = $this->resolveCallback($fn, [$this->valueOrError]);
        if ($resolved instanceof self) {
            return $resolved; // early fail from dependency resolution
        }

        try {
            $res = $fn(...$resolved);
        } catch (Throwable $e) {
            $mapped = $this->normalizeError($mapError, $e);
            if ($mapped instanceof self) {
                return $mapped;
            }
            return $this->fail($mapped);
        }

        if ($res instanceof self) {
            return $this->fail(
                error: new LogicException(
                    "mapTry() expected plain value, got Result. Use bind() instead."
                )
            );
        }

        return new self(
            ok: true,
            valueOrError: $res,
            env: $this->env,
            writer: $this->writer
        );
    }

    // Inspired by crell/fp (typeIs)
    // https://github.com/Crell/fp/blob/master/src/object.php
    public function ofType(
        string $type,
        string|Stringable|Throwable|callable|null $mapError = null
    ): self {
        return $this->bind(function ($value) use ($type, $mapError) {

            $ok = match (true) {
                class_exists($type), interface_exists($type) => $value instanceof $type,
                default => get_debug_type($value) === $type,
            };

            if ($ok) {
                return self::ok($value);
            }

            $error = new TypeError("Expected instance of {$type}");

            // Map the error using shared helper (Env-aware for callables)
            $mapped = $this->normalizeError($mapError, $error);
            if ($mapped instanceof self) {
                return $mapped; // early fail from dependency resolution
            }

            return self::err($mapped);
        });
    }

    // Inspired by crell/fp (prop)
    // https://github.com/Crell/fp/blob/master/src/object.php
    public function prop(
        string $name,
        string|Stringable|Throwable|callable|null $mapError = null
    ): self {
        if ($this->isErr()) {
            return $this;
        }

        $value = $this->valueOrError;

        try {
            if (!is_object($value)) {
                throw new TypeError(sprintf('prop() expects object value, got %s', get_debug_type($value)));
            }

            if (!property_exists($value, $name) && !method_exists($value, '__get')) {
                throw new TypeError(sprintf('Property %s::%s does not exist', $value::class, $name));
            }

            $res = $value->$name; // allow magic __get to run
        } catch (Throwable $e) {
            $mapped = $this->normalizeError($mapError, $e);
            if ($mapped instanceof self) {
                return $mapped; // propagate early failure from env resolution
            }
            return $this->fail($mapped);
        }

        if ($res instanceof self) {
            return $this->fail(new LogicException(
                'prop() expected plain value, got Result.'
            ));
        }

        return new self(
            ok: true,
            valueOrError: $res,
            env: $this->env,
            writer: $this->writer
        );
    }

    // Inspired by crell/fp (method)
    // https://github.com/Crell/fp/blob/master/src/object.php
    public function method(
        string $name,
        array $args = [],
        string|Stringable|Throwable|callable|null $mapError = null
    ): self {
        if ($this->isErr()) {
            return $this;
        }

        $obj = $this->valueOrError;

        // Prepare callable for instance method
        try {
            if (!is_object($obj)) {
                throw new TypeError(sprintf('method() expects object value, got %s', get_debug_type($obj)));
            }
            if (!is_callable([$obj, $name])) {
                throw new TypeError(sprintf('Method %s::%s is not callable or does not exist', $obj::class, $name));
            }

            $callable = [$obj, $name];

            // Resolve arguments via Env (like map/bind/mapTry)
            $resolved = $this->resolveCallback($callable, $args);
            if ($resolved instanceof self) {
                return $resolved; // early fail from dependency resolution
            }

            $res = ($callable)(...$resolved);
        } catch (Throwable $e) {
            $mapped = $this->normalizeError($mapError, $e);
            if ($mapped instanceof self) {
                return $mapped;
            }
            return $this->fail($mapped);
        }

        if ($res instanceof self) {
            return $this->fail(new LogicException(
                'method() expected plain value, got Result.'
            ));
        }

        return new self(
            ok: true,
            valueOrError: $res,
            env: $this->env,
            writer: $this->writer
        );
    }

    // ------------------------------------------------------------
    //  map
    //  Needs to return the modified value inside the bind.
    // ------------------------------------------------------------
    public function map(callable $fn): self
    {
        if ($this->isErr()) {
            return $this;
        }

        $resolved = $this->resolveCallback($fn, [$this->valueOrError]);
        if ($resolved instanceof self) {
            return $resolved; // early fail
        }

        try {
            $res = $fn(...$resolved);
        } catch (TypeError $e) {
            return $this->fail(
                error: new LogicException("map() type error: {$e->getMessage()}")
            );
        }

        if ($res instanceof self) {
            return $this->fail(
                error: new LogicException(
                    "map() expected plain value, got Result. Use bind() instead."
                )
            );
        }

        return new self(
            ok: true,
            valueOrError: $res,
            env: $this->env,
            writer: $this->writer
        );
    }

    // ------------------------------------------------------------
    //  Side-effect without changing a pipeline
    // ------------------------------------------------------------
    public function inspectOk(callable $fn): self
    {
        if ($this->isErr()) {
            return $this;
        }

        // resolve arguments via reflection
        $resolved = $this->resolveCallback($fn, [$this->valueOrError]);
        if ($resolved instanceof self) {
            return $resolved; // early fail
        }

        $fn(...$resolved);

        return $this;
    }

    public function inspectErr(callable $fn): self
    {
        if ($this->isOk()) {
            return $this;
        }

        // resolve arguments via reflection
        $resolved = $this->resolveCallback($fn, [$this->valueOrError]);
        if ($resolved instanceof self) {
            return $resolved; // early fail
        }

        $fn(...$resolved);

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

    public function withEnv(IEnv $env): self
    {
        return new self(
            ok: $this->ok,
            valueOrError: $this->valueOrError,
            env: $this->env->merge($env),
            writer: $this->writer,
        );
    }

    public function withService(object $dependency): self
    {
        return $this->withServices($dependency);
    }

    public function withServices(object ...$dependencies): self
    {
        $env = $this->env;
        foreach ($dependencies as $dep) {
            if (!is_object($dep)) {
                return $this->fail(
                    error: new TypeError(sprintf('withEnv() expects objects as a dependency got %s', gettype($dep)))
                );
            }
            $env = $env->withService($dep);
        }

        return new self(
            ok: $this->ok,
            valueOrError: $this->valueOrError,
            env: $env,
            writer: $this->writer,
        );
    }

    public function withFactory(string $class, callable|string $factory): self
    {
        if (!class_exists($class)) {
            return $this->fail(
                error: new TypeError(sprintf(
                    'withFactory() expects a valid class, got %s',
                    get_debug_type($class)
                ))
            );
        }

        $wrappedFactory = function (Env $env) use ($factory, $class) {

            // class factory (autowire constructor)
            if (is_string($factory)) {
                return $env->make($factory);
            }

            $ref = new ReflectionFunction($factory(...));
            $args = [];

            foreach ($ref->getParameters() as $param) {
                $type = $param->getType();
                $name = $param->getName();

                if ($type instanceof ReflectionNamedType) {
                    $typeName = $type->getName();

                    if (!$type->isBuiltin()) {
                        // Try parameter by name first (can be object)
                        if ($env->hasParameter($name)) {
                            $val = $env->parameter($name);

                            if (is_a($val, $typeName)) {
                                $args[] = $val;
                                continue;
                            }
                        }

                        // Resolve from services/bindings/factories
                        $service = $env->get($typeName);
                        if ($service !== null) {
                            $args[] = $service;
                            continue;
                        }

                        if ($type->allowsNull()) {
                            $args[] = null;
                            continue;
                        }

                        throw new LogicException(sprintf(
                            'Cannot resolve parameter $%s (%s) for factory of %s',
                            $name,
                            $typeName,
                            $class
                        ));
                    }

                    // scalar/builtin parameter by name
                    if ($env->hasParameter($name)) {
                        $args[] = $env->parameter($name);
                        continue;
                    }
                }

                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }

                throw new LogicException(sprintf(
                    'Cannot resolve parameter $%s for factory of %s',
                    $name,
                    $class
                ));
            }

            return $factory(...$args);
        };

        $env = $this->env->withFactory($class, $wrappedFactory);

        return new self(
            ok: $this->ok,
            valueOrError: $this->valueOrError,
            env: $env,
            writer: $this->writer,
        );
    }

    public function withParam(string $name, string|int|float|bool|array $value): self
    {
        if ($name === '') {
            return $this->fail(
                error: new TypeError(sprintf('withParam() expects a non-empty string as parameter name, got %s', get_debug_type($name)))
            );
        }

        $env = $this->env->withParam($name, $value);

        return new self(
            ok: $this->ok,
            valueOrError: $this->valueOrError,
            env: $env,
            writer: $this->writer,
        );
    }

    public function withTag(string $tag, object $service): self
    {
        if (!is_string($tag) || $tag === '') {
            return $this->fail(
                error: new TypeError(sprintf('withTag() expects a non-empty string as tag, got %s', get_debug_type($tag)))
            );
        }

        if (!is_object($service)) {
            return $this->fail(
                error: new TypeError(sprintf('withTag() expects an object as service, got %s', get_debug_type($service)))
            );
        }

        $env = $this->env->withTag($tag, $service);

        return new self(
            ok: $this->ok,
            valueOrError: $this->valueOrError,
            env: $env,
            writer: $this->writer,
        );
    }

    public function withAlias(string $alias, object|string $implementation): self
    {
        if ($alias === '' || (!interface_exists($alias) && !class_exists($alias))) {
            return $this->fail(
                error: new TypeError(sprintf(
                    'withAlias() expects a valid interface or abstract class, got %s',
                    get_debug_type($alias)
                ))
            );
        }

        $implClass = is_object($implementation) ? $implementation::class : $implementation;

        if (!class_exists($implClass)) {
            return $this->fail(
                error: new TypeError(sprintf(
                    'withAlias() expects a valid implementation class for %s, got %s',
                    $alias,
                    get_debug_type($implementation)
                ))
            );
        }

        if (!is_subclass_of($implClass, $alias) && $implClass !== $alias) {
            return $this->fail(
                error: new TypeError(sprintf(
                    'withAlias() expects %s to extend or implement %s',
                    $implClass,
                    $alias
                ))
            );
        }

        $env = $this->env->withAlias($alias, $implementation);

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

    // Shortcut for standardized tracing
    public function withTrace(ITrace $trace): self
    {
        return $this->writeTo(ITraces::class, $trace);
    }

    public function traces(): ITraces
    {
        $traces = TraceCollection::empty();
        foreach ($this->writerOutput(ITraces::class) as $trace) {
            $traces = $traces->add($trace);
        }
        return $traces;
    }

    // Inspired by crell/fp
    // https://github.com/Crell/fp/blob/master/src/composition.php
    public function pipe(callable ...$steps): self
    {
        $r = $this;
        foreach ($steps as $step) {
            $r = $step($r);
        }
        return $r;
    }


    public function ifThenElse(
        callable $condition,
        array $then = [],
        array $else = [],
    ): self {
        if ($this->isErr()) {
            return $this;
        }

        $resolved = $this->resolveCallback($condition, [$this->valueOrError]);
        if ($resolved instanceof self) {
            return $resolved;
        }

        try {
            $result = (bool) ($condition)(...$resolved);
        } catch (TypeError $e) {
            return $this->fail(new LogicException("branch() type error: {$e->getMessage()}"));
        }

        return $result
            ? $this->pipe(...$then)
            : $this->pipe(...$else);
    }


    private function normalizeError(string|Stringable|Throwable|callable|null $mapError, Throwable $base): Throwable|self
    {
        if ($mapError === null) {
            return $base;
        }

        if (is_callable($mapError)) {
            $resolved = $this->resolveCallback($mapError, [$base]);
            if ($resolved instanceof self) {
                return $resolved; // propagate resolution failure
            }
            try {
                $mapped = $mapError(...$resolved);
            } catch (Throwable $e) {
                $mapped = $e; // if mapping throws, use thrown exception
            }
            if (!$mapped instanceof Throwable) {
                $mapped = new Exception((string)$mapped);
            }
            return $mapped;
        }

        if ($mapError instanceof Throwable) {
            return $mapError;
        }

        // string or Stringable
        return new Exception((string)$mapError);
    }

    private function resolveCallback(callable $fn, array $extraArgs = []): array|self
    {
        try {
            if ($fn instanceof Closure) {
                $ref = new ReflectionFunction($fn);
            } elseif (is_array($fn)) {
                $ref = new ReflectionMethod($fn[0], $fn[1]);
            } elseif (is_object($fn) && method_exists($fn, '__invoke')) {
                $ref = new ReflectionMethod($fn, '__invoke');
            } else {
                $ref = new ReflectionFunction($fn);
            }

            $params = $ref->getParameters();
            $args = [];

            foreach ($params as $index => $param) {

                // Use extraArgs first (like current Result value)
                if (isset($extraArgs[$index])) {
                    $args[] = $extraArgs[$index];
                    continue;
                }

                $type = $param->getType();

                if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                    return $this->fail(
                        error: new \LogicException(
                            "Cannot resolve parameter \${$param->getName()} — missing class/interface type"
                        )
                    );
                }

                $dep = $type->getName();
                if (!$this->env->has($dep)) {

                    // allow optional service (?Service)
                    if ($type->allowsNull()) {
                        $args[] = null;
                        continue;
                    }

                    return $this->fail(
                        error: new \LogicException(
                            "Dependency $dep for parameter \${$param->getName()} is not registered in Env"
                        )
                    );
                }

                $service = $this->env->get($dep);

                // If the environment claims to have the dependency but returns null,
                // treat it as missing unless the parameter explicitly allows null.
                if ($service === null && !$type->allowsNull()) {
                    return $this->fail(
                        error: new \LogicException(
                            "Dependency $dep for parameter \${$param->getName()} resolved to null in Env, but the parameter is not nullable"
                        )
                    );
                }

                $args[] = $service;
            }


            return $args;

        } catch (\Throwable $e) {
            return $this->fail($e);
        }
    }
}
