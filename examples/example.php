<?php

declare(strict_types=1);

use Gertvdb\Monad\Result;

/**
 * Functional wrapper around Json serializer.
 */
final class Example
{
    public function __invoke(Result $result): Result
    {
        return $result->bind(
            function (object $object) {
                try {
                    $first = $object->item->first;

                    $extra = new Dep();
                    $result = Result::ok($first);
                    return $result->withEnv($extra);
                } catch (Throwable $e) {
                    $result = Result::err($e);
                    return $result->writeTo(Traces::class, 'error');
                }
            }
        );
    }
}


final class Dep
{
    public function __construct(){}
}

final class Traces
{
    public function __construct(){}
}
