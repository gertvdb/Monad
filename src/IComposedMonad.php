<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Env\IEnv;
use Gertvdb\Monad\Writer\IWriter;

interface IComposedMonad extends IMonad
{

    // ------------------------------------------------------------
    //  Env (Reader)
    // ------------------------------------------------------------
    public function env(): IEnv;
    public function withEnv(object $dependency): self;
    public function bindWithEnv(array $dependencies, callable $fn): self;
    public function mapWithEnv(array $dependencies, callable $fn): self;

    // ------------------------------------------------------------
    //  Writer
    // ------------------------------------------------------------
    public function writer(): IWriter;
    public function writeTo(string $channel, mixed $value): self;
    public function writerOutput(string $channel): array;
}
