<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Writer;

/**
 * Writer effect for collecting side-effects by channel in an immutable way.
 *
 * Typical channels are 'log', 'events', or a fully qualified interface name
 * (e.g. for traces). Implementations should not perform any I/O by themselves;
 * they only accumulate values that can be inspected later.
 *
 * @example Basic usage
 * ```php
 * $writer = new \\Gertvdb\\Monad\\Writer\\Writer();
 * $writer = $writer->write('log', 'started')->write('log', 'finished');
 * $messages = $writer->get('log');
 * ```
 */
interface IWriter
{
    /**
     * Add a value under a channel. Immutable: returns a new Writer.
     *
     * @param string $channel
     * @param mixed $value
     * @return self
     *
     * @example
     * ```php
     * $writer = $writer->write('events', ['type' => 'created']);
     * ```
     */
    public function write(string $channel, mixed $value): self;

    /**
     * Get all values for a key.
     *
     * @param string $channel
     * @return array<int, mixed>
     *
     * @example
     * ```php
     * $events = $writer->get('events');
     * ```
     */
    public function get(string $channel): array;

    /**
     * Merge two writers (values from $other are appended to this writer's values).
     *
     * @param IWriter $other
     * @return self
     */
    public function merge(IWriter $other): self;

    /**
     * Return all channels and values.
     *
     * @return array<string, array<int, mixed>>
     */
    public function all(): array;
}
