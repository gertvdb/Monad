<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Countable;
use Gertvdb\Monad\Env\Env;
use Gertvdb\Monad\Env\IEnv;
use Gertvdb\Monad\Writer\IWriter;
use Gertvdb\Monad\Writer\Writer;
use IteratorAggregate;
use LogicException;
use Traversable;
use TypeError;

final class ResultList implements IResult, IComposedMonad, IteratorAggregate, Countable
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
            : Result::ok($value);

        // Pass env down...
        $newEnv = $this->env;
        $newItem = $newItem->withEnv(...$newEnv->all());

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
    // ------------------------------------------------------------
    public function bind(callable $fn): self
    {
        $out = [];
        $writer = $this->writer;

        $total = count($this->items);
        foreach ($this->items as $index => $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $writer = $writer->merge($item->writer());
                continue;
            }

            try {
                $res = $fn($item->value(), $index, $total);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                $out[] = $item->bind(function () use ($e) {
                    return Result::err(
                        error: $e
                    );
                });
                $writer = $writer->merge($item->writer());
                continue;
            }


            if ($res instanceof Result) {
                $writer = $writer->merge($res->writer());

                if (!$res->isOk()) {
                    $out[] = $res->bind(function () use ($res) {
                        return Result::err(
                            error: $res->error()
                        );
                    });
                } else {
                    $out[] = $res->bind(function () use ($res) {
                        return Result::ok(
                            value: $res->value()
                        );
                    });
                }
                continue;
            }

            $out[] = $res->bind(function () use ($res) {
                return Result::err(
                    error: new LogicException(sprintf(
                        'bindWithEnv() expected a Result return (T -> Result<U>), but got %s. If you want to return a plain value use mapWithEnv() instead.',
                        get_debug_type($res)
                    ))
                );
            });
        }

        $allOk = count($out) === 0 || array_reduce(
            $out,
            fn (bool $carry, Result $r) => $carry && $r->isOk(),
            true
        );

        return new self(
            allOk: $allOk,
            items: $out,
            env: $this->env,
            writer: $writer
        );
    }

    public function bindWithEnv(array $dependencies, callable $fn): self
    {
        $env = [];
        $envErrors = [];

        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                foreach ($this->items as $item) {
                    $envErrors[] = $item->bind(function () use ($dependency) {
                        return Result::err(
                            error: new LogicException(sprintf(
                                'bindWithEnv() failed: missing env for dependency %s',
                                get_debug_type($dependency)
                            ))
                        );
                    });
                }
            }
            $env[$dependency] = $service;
        }

        if (!empty($envErrors)) {
            return new self(
                allOk: false,
                items: $envErrors,
                env: $this->env,
                writer: $this->writer
            );
        }

        $out = [];
        $writer = $this->writer;

        $total = count($this->items);
        foreach ($this->items as $index => $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $writer = $writer->merge($item->writer());
                continue;
            }

            try {
                $res = $fn($item->value(), $env, $index, $total);
            } catch (TypeError $e) {
                // Catch type mismatches in user callback
                $out[] = $item->bind(function () use ($e) {
                    return Result::err(
                        error: new LogicException(sprintf(
                            'bindWithEnv() type error in callback: %s',
                            $e->getMessage()
                        ))
                    );
                });
                $writer = $writer->merge($item->writer());
                continue;
            }

            if ($res instanceof Result) {
                $writer = $writer->merge($res->writer());
                $out[] = $res;
                continue;
            }

            $out[] = $item->bind(function () use ($res) {
                return Result::err(
                    error: new LogicException(sprintf(
                        'bindWithEnv() expected a Result return (T -> Result<U>), but got %s. If you want to return a plain value use mapWithEnv() instead.',
                        get_debug_type($res)
                    ))
                );
            });
        }

        $allOk = count($out) === 0 || array_reduce(
            $out,
            fn (bool $carry, Result $r) => $carry && $r->isOk(),
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
    //  map | mapWithEnv
    // ------------------------------------------------------------
    public function map(callable $fn): self
    {
        $out = [];
        $writer = $this->writer;

        $total = count($this->items);
        foreach ($this->items as $index => $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $writer = $writer->merge($item->writer());
                continue;
            }

            try {
                try {
                    $res = $fn($item->value(), $index, $total);
                } catch (TypeError $e) {
                    // Catch type mismatches in user callback
                    $out[] = $item->bind(function () use ($e) {
                        return Result::err(
                            error: new LogicException(sprintf(
                                'map() type error in callback: %s',
                                $e->getMessage()
                            ))
                        );
                    });
                    $writer = $writer->merge($item->writer());
                    continue;
                }

                // Wrap plain value in Result::ok() and merge writer
                $newItem = $item->bind(function () use ($res) {
                    return Result::ok(
                        value: $res
                    );
                });
                $writer = $writer->merge($item->writer());

                $out[] = $newItem;
            } catch (\Throwable $e) {
                $newItem = $item->bind(function () use ($e) {
                    return Result::err(
                        error: $e
                    );
                });
                $writer = $writer->merge($item->writer());
                $out[] = $newItem;
            }
        }

        $allOk = count($out) === 0 || array_reduce(
            $out,
            fn (bool $carry, Result $r) => $carry && $r->isOk(),
            true
        );

        return new self(
            allOk: $allOk,
            items: $out,
            env: $this->env,
            writer: $writer
        );
    }

    public function mapWithEnv(array $dependencies, callable $fn): self
    {
        $env = [];
        $envErrors = [];

        // Resolve dependencies from env
        foreach ($dependencies as $dependency) {
            $service = $this->env->get($dependency);
            if (!$service) {
                foreach ($this->items as $item) {
                    $envErrors[] = $item->bind(function () use ($dependency) {
                        return Result::err(
                            error: new LogicException(sprintf(
                                'mapWithEnv() failed: missing env for dependency %s',
                                get_debug_type($dependency)
                            ))
                        );
                    });
                }
            }
            $env[$dependency] = $service;
        }

        if (!empty($envErrors)) {
            return new self(
                allOk: false,
                items: $envErrors,
                env: $this->env,
                writer: $this->writer
            );
        }

        $out = [];
        $writer = $this->writer;

        $total = count($this->items);
        foreach ($this->items as $index => $item) {
            if (!$item->isOk()) {
                $out[] = $item;
                $writer = $writer->merge($item->writer());
                continue;
            }

            try {
                // Call user function — user can return plain value

                try {
                    $res = $fn($item->value(), $env, $index, $total);
                } catch (TypeError $e) {
                    // Catch type mismatches in user callback
                    $out[] = $item->bind(function () use ($e) {
                        return Result::err(
                            error: new LogicException(sprintf(
                                'mapWithEnv() type error in callback: %s',
                                $e->getMessage()
                            ))
                        );
                    });

                    $writer = $writer->merge($item->writer());
                    continue;
                }

                // Wrap plain value in Result::ok() and merge writer
                $newItem = $item->bind(function () use ($res) {
                    return Result::ok(
                        value: $res
                    );
                });
                $writer = $writer->merge($item->writer());

                $out[] = $newItem;
            } catch (\Throwable $e) {
                $newItem = $item->bind(function () use ($e) {
                    return Result::err(
                        error: $e
                    );
                });
                $writer = $writer->merge($item->writer());
                $out[] = $newItem;
            }
        }

        $allOk = count($out) === 0 || array_reduce(
            $out,
            fn (bool $carry, Result $r) => $carry && $r->isOk(),
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
    //  Inspect (side-effects only)
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
        $out = array_filter($this->items, fn (Result $r) => $r->isOk());
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
            if ($r->isErr()) {
                throw $r->unwrapErr();
            }
        }
        return array_map(fn (Result $r) => $r->value(), $this->items);
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
                    $out[] = $item->bind(function () use ($err) {
                        return Result::err(
                            error: $err
                        );
                    });
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
            allOk: $this->allOk,
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
