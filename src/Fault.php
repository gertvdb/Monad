<?php

namespace GertVdb\Monad;

use Throwable;

class Fault
{
    public readonly string $message;
    public readonly int $code;
    public readonly ?Throwable $previous;

    private function __construct(
        string     $message = "",
        int        $code = 0,
        ?Throwable $previous = null,
    ) {
        $this->message = $message;
        $this->code = $code;
        $this->previous = $previous;
    }

    public static function dueTo(
        string $message,
        int $code = 0,
        Throwable|null $previous = null,
    ): Fault {
        return new self(
            message: $message,
            code: $code,
            previous: $previous,
        );
    }
}
