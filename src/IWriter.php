<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

interface IWriter
{
    /**
     * Add a value under a channel. Immutable: returns a new Writer.
     *
     * @param string $channel
     * @param mixed $value
     */
    public function write(string $channel, mixed $value): self;

    /**
     * Get all values for a key.
     *
     * @param string $channel
     * @return array<int, mixed>
     */
    public function get(string $channel): array;

    /**
     * @param IWriter $other
     * @return self
     */
    public function merge(IWriter $other): self;

    /**
     * @return array
     */
    public function all(): array;
}
