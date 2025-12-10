<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use PHPUnit\Framework\TestCase;
use ValueError;

final class OptionTest extends TestCase
{
    public function testSomeAndNonePredicates(): void
    {
        $some = Option::some(123);
        $none = Option::none();

        self::assertTrue($some->isSome());
        self::assertFalse($some->isNone());
        self::assertFalse($none->isSome());
        self::assertTrue($none->isNone());
    }

    public function testUnwrapVariants(): void
    {
        $some = Option::some('x');
        $none = Option::none();

        self::assertSame('x', $some->unwrap());
        self::assertSame('x', $some->unwrapOr('y'));
        self::assertSame('x', $some->unwrapOrElse(fn () => 'z'));
        self::assertSame('x', $some->value());

        self::assertNull($none->value());
        self::assertSame('default', $none->unwrapOr('default'));
        self::assertSame('computed', $none->unwrapOrElse(fn () => 'computed'));

        $this->expectException(ValueError::class);
        $none->unwrap();
    }

    public function testMapReturnsPlainValue(): void
    {
        $some = Option::some(2)->map(fn (int $v) => $v * 2);
        self::assertTrue($some->isSome());
        self::assertSame(4, $some->unwrap());

        $none = Option::none()->map(fn ($v) => $v * 2);
        self::assertTrue($none->isNone());
    }

    public function testMapReturningOptionFails(): void
    {
        $result = Option::some(1)->map(fn (int $v) => Option::some($v + 1));
        self::assertTrue($result->isNone(), 'map should fail when returning Option');
    }

    public function testBindExpectsOption(): void
    {
        $ok = Option::some(10)->bind(function (int $v) {
            return Option::some($v + 5);
        });
        self::assertTrue($ok->isSome());
        self::assertSame(15, $ok->unwrap());

        $fail = Option::some(10)->bind(fn (int $v) => $v + 5); // returns plain value -> None
        self::assertTrue($fail->isNone());

        $none = Option::none()->bind(fn ($v) => Option::some($v));
        self::assertTrue($none->isNone());
    }

    public function testBindCatchesTypeErrorAndThrowable(): void
    {
        // TypeError inside callback yields None
        $r1 = Option::some(10)->bind(function (string $s) {
            return Option::some($s);
        });
        self::assertTrue($r1->isNone());

        // Any thrown exception yields None
        $r2 = Option::some(10)->bind(function (int $v) {
            throw new \RuntimeException('boom');
        });
        self::assertTrue($r2->isNone());
    }

    public function testWithEnvAndMapWithEnvAndBindWithEnv(): void
    {
        $dep = new class() { public function inc(int $v): int { return $v + 1; } };

        $start = Option::some(1)->withEnv($dep);

        // mapWithEnv uses the env and returns plain value
        $mapped = $start->mapWithEnv([get_class($dep)], function (int $v, array $env) use ($dep) {
            $svc = $env[get_class($dep)];
            return $svc->inc($v);
        });
        self::assertTrue($mapped->isSome());
        self::assertSame(2, $mapped->unwrap());

        // bindWithEnv uses the env and returns Option
        $bound = $start->bindWithEnv([get_class($dep)], function (int $v, array $env) use ($dep) {
            $svc = $env[get_class($dep)];
            // call inc via dynamic access
            return Option::some($svc->inc($v));
        });
        self::assertTrue($bound->isSome());
        self::assertSame(2, $bound->unwrap());

        // missing dependency → None
        $missing = $start->mapWithEnv([\stdClass::class], fn ($v, $env) => $v);
        self::assertTrue($missing->isNone());

        $missing2 = $start->bindWithEnv([\stdClass::class], fn ($v, $env) => Option::some($v));
        self::assertTrue($missing2->isNone());
    }

    public function testWriterIntegrationAndInspectSome(): void
    {
        $collector = [];
        $some = Option::some('a')
            ->inspectSome(function ($v) use (&$collector) { $collector[] = $v; })
            ->writeTo('log', 'first')
            ->map(fn ($v) => strtoupper($v))
            ->writeTo('log', 'second');

        self::assertSame(['a'], $collector);
        self::assertSame(['first', 'second'], $some->writerOutput('log'));

        // Writers merge across bind
        $b = Option::some('x')->writeTo('log', 'a')
            ->bind(function (string $s) {
                return Option::some($s . 'y')->writeTo('log', 'b');
            });
        self::assertSame(['a', 'b'], $b->writerOutput('log'));
    }

    public function testWithEnvRejectsNonObject(): void
    {
        $this->expectException(\TypeError::class);
        Option::some(1)->withEnv(1); // non object triggers parameter type error
    }
}
