<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Trace;

use Throwable;

final class TraceException implements Trace
{
    private function __construct(
        private readonly \Throwable $exeption,
        private readonly int $at
    ) {
    }

    public static function from(
        Throwable $exeption,
        int $at
    ): self {
        return new self(
            $exeption,
            $at
        );
    }

    public function read(): string
    {
        return sprintf(
            '%s | %s | %s | %s',
            $this->exeption->getFile(),
            $this->exeption->getLine(),
            $this->exeption->getMessage(),
            $this->exeption->getTraceAsString()
        );
    }

    public function at(): int
    {
        return $this->at;
    }
}
