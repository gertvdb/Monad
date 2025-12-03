<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Stringable;
use Throwable;

interface IResult
{
    // ------------------------------------------------------------
    //  Basic state
    // ------------------------------------------------------------
    public function isOk(): bool;
    public function isErr(): bool;

    // ------------------------------------------------------------
    //  Change
    // ------------------------------------------------------------
    public function lift(mixed $value): self;

    // ------------------------------------------------------------
    //  Bind | Map
    // ------------------------------------------------------------
    public function bind(callable $fn): self;
    public function map(callable $fn): self;

    // ------------------------------------------------------------
    //  Side-effects
    // ------------------------------------------------------------
    public function inspectOk(callable $fn): self;
    public function inspectErr(callable $fn): self;

    // ------------------------------------------------------------
    //  Unwrap
    // ------------------------------------------------------------
    public function unwrap(): mixed;

    // ------------------------------------------------------------
    //  Env (Reader)
    // ------------------------------------------------------------
    public function env(): Env;
    public function withEnv(object $dependency): self;
    public function bindWithEnv(array $dependencies, callable $fn): self;
    public function mapWithEnv(array $dependencies, callable $fn): self;

    // ------------------------------------------------------------
    //  Writer
    // ------------------------------------------------------------
    public function writer(): Writer;
    public function writeTo(string $channel, mixed $value): self;
    public function writerOutput(string $channel): array;
}
