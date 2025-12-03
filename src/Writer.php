<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

final class Writer implements IWriter
{
    /** @var array<string, array<int, mixed>> */
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Create an empty Writer.
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Add a value under a key. Returns a new Writer.
     */
    public function write(string $channel, mixed $value): self
    {
        $newData = $this->data;

        // Append the value to the array at the given key
        $newData[$channel][] = $value;

        return new self($newData);
    }

    /**
     * Get all values for a key. Returns empty array if none.
     */
    public function get(string $channel): array
    {
        return $this->data[$channel] ?? [];
    }

    /**
     * Merge another Writer into this one. Returns a new Writer.
     */
    public function merge(IWriter $other): self
    {
        $newData = $this->data;

        foreach ($other->all() as $channel => $values) {
            foreach ($values as $value) {
                $newData[$channel][] = $value;
            }
        }

        return new self($newData);
    }

}
