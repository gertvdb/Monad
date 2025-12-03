<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Countable;
use Exception;
use IteratorAggregate;
use LogicException;
use Stringable;
use Throwable;
use Traversable;
use TypeError;

final class ListResult implements IResult, IteratorAggregate, Countable
{
    /** @var Result[] */
    private array $items;

    private function __construct(
        private readonly bool $allOk,
        array                 $items,
        private Env           $env,
        private Writer        $writer,
    ) {
        $this->items = array_values($items);
    }

    // ------------------------------------------------------------
    //  Constructors
    // ------------------------------------------------------------

    /**
     * Create ListResult from array of values
     * Wrap each value in Result::ok with context when it is not a Result.
     **/
    public static function of(
        array   $values,
        ?Env    $env = null,
        ?Writer $writer = null
    ): self {
        $env = $env ?? Env::empty();
        $writer = $writer ?? Writer::empty();

        $items = array_map(
            function ($v) use ($env, $writer) {
                $env = $env->merge($v->contexts());

                if ($v instanceof Result) {
                    if ($v->isOk()) {
                        return Result::ok(
                            $v->value(),
                            $env,
                            $writer
                        );
                    }

                    return Result::err(
                        $v->error(),
                        $env,
                        $writer
                    );
                }

                return Result::ok(
                    $v,
                    $env,
                    $writer
                );
            },
            $values,
        );

        $allOk = count($items) === 0 || array_reduce($items, fn ($carry, Result $r) => $carry && $r->isOk(), true);

        return new self(
            allOk: $allOk,
            items: $items,
            env: $env,
            writer: $writer,
        );
    }

    public function lift(mixed $value): self
    {
        if ($this->isErr()) {
            return $this;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        return self::of($value, $this->env, $this->writer);
    }

    /**
     * fail() produces a new ResultList with the passed error and keeps env and writer.
     */
    public function fail(string|Stringable|Throwable $error): self
    {
        $dueTo = $error instanceof Throwable ? $error : new Exception((string)$error);
        return self::of(
            values: [$dueTo],
            env: $this->env,
            writer: $this->writer,
        );
    }

    // ------------------------------------------------------------
    //  Basic state
    // ------------------------------------------------------------

    public function isOk(): bool
    {
        return $this->allOk;
    }
    public function isErr(): bool
    {
        return !$this->allOk;
    }


    // ------------------------------------------------------------
    //  bind | bindWithContext
    //  Needs to return a Result inside the bind.
    //  Change value with with() to keep context.
    // ------------------------------------------------------------

    public function bind(callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer; // start with parent writer

        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                continue;
            }

            $child = $item->bind($fn);

            // accumulate child writer
            $newWriter = $newWriter->merge($child->writer());

            $out[] = $child;
        }

        return self::of(
            values: $out,
            env: $this->env,
            writer: $newWriter,
        );
    }

    public function bindWithEnv(array $dependencies, callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer; // start with parent writer

        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                continue;
            }

            $child = $item->bindWithEnv($dependencies, $fn);

            // accumulate child writer
            $newWriter = $newWriter->merge($child->writer());

            $out[] = $child;
        }

        return self::of(
            values: $out,
            env: $this->env,
            writer: $newWriter,
        );
    }


    // ------------------------------------------------------------
    //  map | mapWithContext
    //  Needs to return the modified value inside the bind.
    // ------------------------------------------------------------

    public function map(callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer; // start with parent writer

        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                continue;
            }

            $child = $item->map($fn);

            // accumulate child writer
            $newWriter = $newWriter->merge($child->writer());

            $out[] = $child;
        }

        return self::of(
            values: $out,
            env: $this->env,
            writer: $newWriter,
        );
    }

    public function mapWithEnv(array $dependencies, callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer; // start with parent writer

        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                continue;
            }

            $child = $item->mapWithEnv($dependencies, $fn);

            // accumulate child writer
            $newWriter = $newWriter->merge($child->writer());

            $out[] = $child;
        }

        return self::of(
            values: $out,
            env: $this->env,
            writer: $newWriter,
        );
    }

    // ------------------------------------------------------------
    //  Side-effect without changing a pipeline
    //  (ex: $result->inspectOk(fn($value) => var_dump($value));
    // ------------------------------------------------------------
    public function inspectOk(callable $fn): self
    {
        foreach ($this->items as $r) {
            if ($r->isOk()) {
                $r->inspectOk($fn);
            }
        }

        return $this;
    }

    public function inspectErr(callable $fn): self
    {
        foreach ($this->items as $r) {
            if ($r->isErr()) {
                $r->inspectErr($fn);
            }
        }

        return $this;
    }

    // ------------------------------------------------------------
    //  Filter
    // ------------------------------------------------------------

    public function filterOk(): self
    {
        $out = [];
        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                continue;
            }
            $out[] = $item;
        }

        return self::of(
            values: $out,
            env: $this->env,
            writer: $this->writer,
        );
    }

    // ------------------------------------------------------------
    // Collect / Traverse
    // ------------------------------------------------------------

    /** collect :: Result::ok([T, T, ...]) || Result::err('first error') */
    public function collect(): Result
    {
        foreach ($this->items as $r) {
            if ($r->isErr()) {
                return $r;
            }
        }

        // All OK — unwrap the values
        $values = array_filter(array_map(fn (Result $r) => $r->value(), $this->items));

        return Result::ok(
            value: $values,
            env: $this->env,
            writer: $this->writer,
        );
    }

    /** collect :: Result::ok([T, T, ...]) */
    public function collectOk(): Result
    {
        $out = [];
        foreach ($this->items as $r) {
            if (!$r->isErr()) {
                $out[] = $r->value();
            }
        }

        return Result::ok(
            value: $out,
            env: $this->env,
            writer: $this->writer,
        );
    }


    // ------------------------------------------------------------
    // Unwrapping
    // ------------------------------------------------------------

    /** Returns list of T objects */
    public function unwrap(): array
    {
        foreach ($this->items as $r) {
            if ($r->isErr()) {
                throw $r;
            }
        }
        return array_filter(array_map(fn (Result $r) => $r->value(), $this->items));
    }


    // ------------------------------------------------------------
    // Head / Only
    // ------------------------------------------------------------

    public function head(): Result
    {
        if (empty($this->items)) {
            return Result::err(
                new LogicException("Cannot call head() on empty ListResult")
            );
        }
        return $this->items[0];
    }

    public function only(): Result
    {
        $count = count($this->items);
        if ($count !== 1) {
            return Result::err(
                new LogicException(sprintf("ListResult::only() expects exactly 1 element, got %s", $count))
            );
        }
        return $this->items[0];
    }

    // ------------------------------------------------------------
    //  Env (Reader)
    // ------------------------------------------------------------
    public function env(): Env
    {
        return $this->env;
    }

    public function withEnv(object ...$dependencies): self
    {
        $newEnv = $this->env;
        foreach ($dependencies as $dep) {
            if (!is_object($dep)) {
                $err = new TypeError(sprintf('withEnv() expects objects as a dependency got %s', gettype($dep)));
                return $this->fail($err);
            }
            $newEnv = $newEnv->with($dep);
        }

        return self::of(
            values: $this->items,
            env: $newEnv,
            writer: $this->writer,
        );
    }

    // ------------------------------------------------------------
    //  Writer
    // ------------------------------------------------------------
    public function writer(): Writer
    {
        return $this->writer;
    }

    public function writeTo(string $channel, mixed $value): IResult
    {
        // new writer for parent
        $newWriter = $this->writer->write($channel, $value);

        // propagate the write to every child Result
        $newItems = array_map(
            fn (IResult $r) => $r->mergeWriter($newWriter),
            $this->items
        );

        return self::of(
            values : $newItems,
            env   : $this->env,
            writer: $newWriter,
        );
    }

    public function writerOutput(string $channel): array
    {
        return $this->writer->get($channel);
    }


    // ------------------------------------------------------------
    //  Iterator (immutable)
    // ------------------------------------------------------------

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    // ------------------------------------------------------------
    //  Countable
    // ------------------------------------------------------------

    public function count(): int
    {
        return count($this->items);
    }
}
