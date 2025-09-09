<?php

declare(strict_types=1);

use Gertvdb\Monad\Context\Context;
use Gertvdb\Monad\Context\ContextCollection;
use Gertvdb\Monad\Context\ContextType;
use Gertvdb\Monad\Either;
use Gertvdb\Monad\Monads\Either\Failure;
use Gertvdb\Monad\Trace\TraceCommon;

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

    public function type(): ContextType
    {
        return ContextType::Persistent;
    }
}
