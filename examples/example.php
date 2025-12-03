<?php

declare(strict_types=1);

use Gertvdb\Monad\Result;
use Gertvdb\Monad\Trace\TraceException;
use Gertvdb\Monad\Trace\Traces;

/**
 * Functional wrapper around Json serializer.
 */
final class Example
{
    public function __invoke(Result $result): Result
    {
        return $result->bind(
            function (object $object) use ($result) {
                try {
                    $first = $object->item->first;
                    $extra = new Dep();
                    $result = $result->withEnv($extra);

                    return $result->lift($first);
                } catch (Throwable $e) {

                    $result = $result->writeTo(Traces::class, TraceException::from($e, time()));
                    return $result->fail($e);
                }
            }
        );
    }
}


final class Dep
{
    public function __construct(){}
}
