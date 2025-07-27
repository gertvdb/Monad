<?php

declare(strict_types=1);

namespace GertVdb\Monad\Trace;

final class TraceCommon implements Trace
{
    private function __construct(
        private readonly string $message,
        private readonly int $at
    ) {
    }

    public static function from(
        string $message,
        int $at
    ): TraceCommon {
        return new TraceCommon(
            message: $message,
            at: $at
        );
    }

    public function read(): string
    {
        return $this->message;
    }

    public function at(): int
    {
        return $this->at;
    }
}
