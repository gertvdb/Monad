<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Countable;
use IteratorAggregate;
use LogicException;
use Traversable;
use TypeError;

final class ListResult implements IResult, IteratorAggregate, Countable
{
    /** @var Result[] */
    private array $items;

    private function __construct(
        private readonly bool    $allOk,
        array                    $items,
        private readonly IEnv    $env,
        private readonly IWriter $writer,
    ) {
        $this->items = array_values($items);
    }

    // ------------------------------------------------------------
    //  Constructors
    // ------------------------------------------------------------

    public static function empty(
        ?IEnv    $env = null,
        ?IWriter $writer = null
    ): self {
        $env = $env ?? Env::empty();
        $writer = $writer ?? Writer::empty();
        return new self(
            allOk: true,
            items: [],
            env: $env,
            writer: $writer,
        );
    }

    /**
     * Create ListResult from array of values
     * Wrap each value in Result::ok with env when it is not a Result.
     **/
    public static function of(
        array   $values,
        ?IEnv    $env = null,
        ?IWriter $writer = null
    ): self {
        $env = $env ?? Env::empty();
        $parentWriter = $writer ?? Writer::empty();

        $new = self::empty($env, $parentWriter);
        foreach ($values as $value) {
            $new = $new->add($value);
        }

        return $new;
    }

    public function add(mixed $value): self
    {
        $childWriter = ListResultWriter::empty($this->writer);
        $newItem = Result::ok($value, $this->env, $childWriter);

        $newItems = array_merge($this->items, [$newItem]);

        return new self(
            allOk: $this->allOk, // adding 1 oke, will not change state.
            items: $newItems,
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
    //  bind | bindWithEnv
    //  Needs to return a Result inside the bind.
    //  Change value with with() to keep env.
    // ------------------------------------------------------------

    public function bind(callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer;
        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $newWriter = $newWriter->merge($item->writer());

                continue;
            }

            $child = $item->bind($fn);
            $out[] = $child;
            $newWriter = $newWriter->merge($child->writer());
        }

        $allOk = count($out) === 0 || array_reduce($out, fn ($carry, Result $r) => $carry && $r->isOk(), true);

        return new self(
            allOk: $allOk,
            items: $out,
            env: $this->env,
            writer: $newWriter,
        );
    }

    public function bindWithEnv(array $dependencies, callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer;
        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $newWriter = $newWriter->merge($item->writer());
                continue;
            }

            $child = $item->bindWithEnv($dependencies, $fn);
            $out[] = $child;
            $newWriter = $newWriter->merge($child->writer());
        }

        $allOk = count($out) === 0 || array_reduce($out, fn ($carry, Result $r) => $carry && $r->isOk(), true);

        return new self(
            allOk: $allOk,
            items: $out,
            env: $this->env,
            writer: $newWriter,
        );
    }


    // ------------------------------------------------------------
    //  map | mapWithEnv
    //  Needs to return the modified value inside the bind.
    // ------------------------------------------------------------

    public function map(callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer;

        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $newWriter = $newWriter->merge($item->writer());
                continue;
            }

            $child = $item->map($fn);
            $out[] = $child;
            $newWriter = $newWriter->merge($child->writer());
        }

        $allOk = count($out) === 0 || array_reduce($out, fn ($carry, Result $r) => $carry && $r->isOk(), true);

        return new self(
            allOk: $allOk,
            items: $out,
            env: $this->env,
            writer: $newWriter,
        );
    }

    public function mapWithEnv(array $dependencies, callable $fn): self
    {
        $out = [];
        $newWriter = $this->writer;

        foreach ($this->items as $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $newWriter = $newWriter->merge($item->writer());
                continue;
            }

            $child = $item->mapWithEnv($dependencies, $fn);
            $out[] = $child;
            $newWriter = $newWriter->merge($child->writer());
        }

        $allOk = count($out) === 0 || array_reduce($out, fn ($carry, Result $r) => $carry && $r->isOk(), true);

        return new self(
            allOk: $allOk,
            items: $out,
            env: $this->env,
            writer: $this->writer,
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

        return new self(
            allOk: true,
            items: $out,
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
    public function env(): IEnv
    {
        return $this->env;
    }

    public function withEnv(object ...$dependencies): self
    {
        $out = $this->items;
        $newEnv = $this->env;
        foreach ($dependencies as $dep) {
            if (!is_object($dep)) {
                $err = new TypeError(sprintf('withEnv() expects objects as a dependency got %s', gettype($dep)));
                foreach ($this->items as $item) {
                    if (!$item->isOk()) {
                        $out[] = $item;
                    }
                    $out[] = Result::err(
                        error: $err,
                        env: $this->env,
                        writer: $this->writer,
                    );
                }
            }
            $newEnv = $newEnv->with($dep);
        }

        $allOk = count($out) === 0 || array_reduce($out, fn ($carry, Result $r) => $carry && $r->isOk(), true);

        return new self(
            allOk: $allOk,
            items: $out,
            env: $newEnv,
            writer: $this->writer,
        );
    }

    // ------------------------------------------------------------
    //  Writer
    // ------------------------------------------------------------
    public function writer(): IWriter
    {
        return $this->writer;
    }

    public function writeTo(string $channel, mixed $value): IResult
    {
        // new writer for parent
        $newWriter = $this->writer->write($channel, $value);

        return new self(
            allOk: true,
            items: $this->items,
            env: $this->env,
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
