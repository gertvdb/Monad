<?php

declare(strict_types=1);

namespace Gertvdb\Monad\Context;

enum ContextType: string
{
    case Persistent = 'persistent';
    case Transient = 'transient';

    /**
     * Utility method to check if a context is persistent
     * This will keep the context after it's been read.
     */
    public function isPersistent(): bool
    {
        return $this === self::Persistent;
    }

    /**
     * Utility method to check if a context is transient
     * This will remove the context after it's been read.
     */
    public function isTransient(): bool
    {
        return $this === self::Transient;
    }
}
