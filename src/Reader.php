<?php

declare(strict_types=1);

namespace GertVdb\Monad;

use GertVdb\Monad\Context\Context;

/**
 * Monad including reader functionality.
 *
 * @template T of Reader
 */
interface Reader
{
    /**
     * @return T
     */
    public function withContext(Context $context): Reader;

    public function context(string $class): Optional;

    /**
     * @return T
     */
    public function clearContext(string $class): Reader;
}
