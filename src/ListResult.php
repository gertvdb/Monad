<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Countable;
use IteratorAggregate;
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
    public static function empty(?IEnv $env = null, ?IWriter $writer = null): self
    {
        return new self(
            allOk: true,
            items: [],
            env: $env ?? Env::empty(),
            writer: $writer ?? Writer::empty(),
        );
    }

    public static function of(iterable $values, ?IEnv $env = null, ?IWriter $writer = null): self
    {
        $env = $env ?? Env::empty();
        $writer = $writer ?? Writer::empty();
        $new = self::empty($env, $writer);

        foreach ($values as $value) {
            $new = $new->add($value);
        }
        return $new;
    }

    // ------------------------------------------------------------
    //  Add item
    // ------------------------------------------------------------
    public function add(mixed $value): self
    {
        $newItem = ($value instanceof Result)
            ? $value
            : Result::ok($value, $this->env, Writer::empty());

        return new self(
            allOk: $this->allOk && $newItem->isOk(),
            items: [...$this->items, $newItem],
            env: $this->env,
            writer: $this->writer->merge($newItem->writer()),
        );
    }

    // ------------------------------------------------------------
    //  State
    // ------------------------------------------------------------
    public function isOk(): bool { return $this->allOk; }
    public function isErr(): bool { return !$this->allOk; }

    // ------------------------------------------------------------
    //  Shared internal reducer for bind/map operations
    // ------------------------------------------------------------
    private function applyOverItems(callable $operation): self
    {
        $out = [];
        $writer = $this->writer;

        foreach ($this->items as $item) {

            // Leave error items untouched but merge writer
            if (!$item->isOk()) {
                $out[] = $item;
                $writer = $writer->merge($item->writer());
                continue;
            }

            // Apply the operation (bind / bindWithEnv / map / mapWithEnv)
            $child = $operation($item);
            $out[] = $child;

            // Merge writer from the child result
            $writer = $writer->merge($child->writer());
        }

        // Compute total OK-state
        $allOk = count($out) === 0 || array_reduce(
                $out,
                fn(bool $carry, Result $r) => $carry && $r->isOk(),
                true
            );

        return new self(
            allOk: $allOk,
            items: $out,
            env: $this->env,
            writer: $writer
        );
    }

    // ------------------------------------------------------------
    //  bind | bindWithEnv
    // ------------------------------------------------------------
    public function bind(callable $fn): self
    {
        return $this->applyOverItems(
            fn(Result $r) => $r->bind($fn)
        );
    }

    public function bindWithEnv(array $dependencies, callable $fn): self
    {
        return $this->applyOverItems(
            fn(Result $r) => $r->bindWithEnv($dependencies, $fn)
        );
    }

    // ------------------------------------------------------------
    //  map | mapWithEnv
    // ------------------------------------------------------------
    public function map(callable $fn): self
    {
        return $this->applyOverItems(
            fn(Result $r) => $r->map($fn)
        );
    }

    public function mapWithEnv(array $dependencies, callable $fn): self
    {
        return $this->applyOverItems(
            fn(Result $r) => $r->mapWithEnv($dependencies, $fn)
        );
    }

    // ------------------------------------------------------------
    //  Inspect (side-effects only)
    // ------------------------------------------------------------
    public function inspectOk(callable $fn): self
    {
        foreach ($this->items as $r) {
            if ($r->isOk()) $r->inspectOk($fn);
        }
        return $this;
    }

    public function inspectErr(callable $fn): self
    {
        foreach ($this->items as $r) {
            if ($r->isErr()) $r->inspectErr($fn);
        }
        return $this;
    }

    // ------------------------------------------------------------
    //  Filter
    // ------------------------------------------------------------
    public function filterOk(): self
    {
        $out = array_filter($this->items, fn(Result $r) => $r->isOk());
        return new self(
            allOk: true,
            items: $out,
            env: $this->env,
            writer: $this->writer,
        );
    }

    // ------------------------------------------------------------
    //  Unwrap
    // ------------------------------------------------------------
    public function unwrap(): array
    {
        foreach ($this->items as $r) {
            if ($r->isErr()) throw $r;
        }
        return array_map(fn(Result $r) => $r->value(), $this->items);
    }

    // ------------------------------------------------------------
    //  Env
    // ------------------------------------------------------------
    public function env(): IEnv
    {
        return $this->env;
    }

    public function withEnv(object ...$dependencies): self
    {
        $newEnv = $this->env;
        $out = [];

        foreach ($dependencies as $dep) {
            if (!is_object($dep)) {
                $err = new TypeError(
                    sprintf('withEnv() expects objects, got %s', gettype($dep))
                );

                foreach ($this->items as $item) {
                    $out[] = Result::err($err, env: $this->env, writer: $this->writer);
                }

                return new self(
                    allOk: false,
                    items: $out,
                    env: $this->env,
                    writer: $this->writer
                );
            }
            $newEnv = $newEnv->with($dep);
        }

        return new self(
            allOk: $this->allOk,
            items: $this->items,
            env: $newEnv,
            writer: $this->writer
        );
    }

    // ------------------------------------------------------------
    //  Writer
    // ------------------------------------------------------------
    public function writer(): IWriter
    {
        return $this->writer;
    }

    public function writeTo(string $channel, mixed $value): self
    {
        return new self(
            allOk: true,
            items: $this->items,
            env: $this->env,
            writer: $this->writer->write($channel, $value),
        );
    }

    public function writerOutput(string $channel): array
    {
        return $this->writer->get($channel);
    }

    // ------------------------------------------------------------
    //  Iterator & Countable
    // ------------------------------------------------------------
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
