<?php

declare(strict_types=1);

use Tactics\Monad\Context\Context;
use Tactics\Monad\Context\ContextCollection;
use Tactics\Monad\Either;
use Tactics\Monad\Monads\Either\Failure;
use Tactics\Monad\Trace\TraceCommon;

/**
 * Functional wrapper around Json serializer.
 */
final class Example
{
    public function __invoke(Either $result): Either
    {
        return $result->bind(
            function (object $object) use ($result) {
                try {
                    $first = $object->item->first;
                    return $result->lift($first);
                } catch (Throwable $e) {
                    $contexts = ContextCollection::empty();

                    $extra = new MyExtraCustomErrorContext();
                    $contexts = $contexts->add($extra);

                    return Failure::dueTo(
                        message: 'My error',
                        code: 500,
                        previous: $e,
                        trace: TraceCommon::from('Extra trace', time()),
                        traces: $result->traces(),
                        contexts: $contexts
                    );
                }
            }
        );
    }
}


final class MyExtraCustomErrorContext implements Context
{
    public function __construct(){}
}
