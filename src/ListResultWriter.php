<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

final class ListResultWriter implements IWriter
{

    private IWriter $parentWriter;

    /** @var array<string, array<int, mixed>> */
    public readonly array $data;

    public function __construct(
        IWriter $parentWriter,
        array $data = [])
    {
        $this->parentWriter = $parentWriter;
        $this->data = $data;
    }

    /**
     * Create an empty Writer.
     */
    public static function empty(IWriter $parentWriter): self
    {
        return new self($parentWriter, []);
    }

    /**
     * Add a value under a key. Returns a new Writer.
     */
    public function write(string $channel, mixed $value): self
    {
        $newData = $this->data;

        // Append the value to the array at the given key
        $newData[$channel][] = $value;

        return new self($this->parentWriter, $newData);
    }

    /**
     * Merge another Writer into this one. Returns a new Writer.
     */
    public function merge(IWriter $other): self
    {
        $newData = $this->data;
        foreach ($other->data as $channel => $values) {
            foreach ($values as $value) {
                $newData[$channel][] = $value;
            }
        }

        return new self($this->parentWriter, $newData);
    }

    /**
     * Get all values for a key. Returns empty array if none.
     */
    public function get(string $channel): array
    {
        $parentData = $this->parentWriter->get($channel);
        $childData = $this->data[$channel] ?? [];
        return array_merge($parentData, $childData);
    }

    public function all(): array
    {
        $parentData = $this->parentWriter->all();
        return array_merge_recursive($parentData, $this->data);
    }
}
