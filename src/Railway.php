<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

final readonly class Railway
{
    /**
     * @param array<string, callable(Either): Either> $handlers
     */
    public function __construct(private array $handlers){}

    public function __invoke(Either $result, string $context, callable $extractKey): Either
    {
        return $this->run($result, $context, $extractKey);
    }

    /**
     * @template T
     * @param Either<T> $result
     * @param string $contextClass
     * @param callable(object): string $extractKey
     * @return Either<T|string>
     */
    public function run(Either $result, string $context, callable $extractKey): Either
    {
        return $result->bindWithContext([$context], function ($input, $contexts) use ($result, $extractKey, $context) {
            $key = $extractKey($contexts[$context]);

            if (!isset($this->handlers[$key])) {
                return $result->fail(Fault::dueTo("Unsupported key: $key"));
            }

            return $this->handlers[$key]($result);
        });
    }
}
