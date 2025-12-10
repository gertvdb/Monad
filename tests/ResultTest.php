<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use LogicException;
use PHPUnit\Framework\TestCase;

final class ResultTest extends TestCase
{
    public function testOkAndErrPredicatesAndAccessors(): void
    {
        $ok = Result::ok(123);
        $err = Result::err('bad');

        self::assertTrue($ok->isOk());
        self::assertFalse($ok->isErr());
        self::assertFalse($err->isOk());
        self::assertTrue($err->isErr());

        self::assertSame(123, $ok->unwrap());
        self::assertSame(123, $ok->unwrapOr(0));
        self::assertSame(123, $ok->unwrapOrElse(fn () => 0));
        self::assertSame(123, $ok->value());
        self::assertNull($ok->error());

        self::assertNull($err->value());
        self::assertInstanceOf(\Throwable::class, $err->error());
        self::assertSame('fallback', $err->unwrapOr('fallback'));
        self::assertSame('computed', $err->unwrapOrElse(fn () => 'computed'));

        $this->expectException(\Throwable::class);
        $err->unwrap();
    }

    public function testUnwrapErr(): void
    {
        $err = Result::err('boom');
        self::assertInstanceOf(\Throwable::class, $err->unwrapErr());

        $this->expectException(LogicException::class);
        Result::ok(1)->unwrapErr();
    }

    public function testMapAndBindBasics(): void
    {
        $mapped = Result::ok(2)->map(fn (int $v) => $v * 3);
        self::assertTrue($mapped->isOk());
        self::assertSame(6, $mapped->unwrap());

        $mappedErr = Result::err('x')->map(fn ($v) => $v);
        self::assertTrue($mappedErr->isErr());

        $mapReturnResult = Result::ok(1)->map(fn (int $v) => Result::ok($v + 1));
        self::assertTrue($mapReturnResult->isErr(), 'map returning Result should fail');
        self::assertInstanceOf(LogicException::class, $mapReturnResult->unwrapErr());

        $bound = Result::ok(10)->bind(fn (int $v) => Result::ok($v + 5));
        self::assertTrue($bound->isOk());
        self::assertSame(15, $bound->unwrap());

        $bindPlain = Result::ok(10)->bind(fn (int $v) => $v + 5);
        self::assertTrue($bindPlain->isErr());
        self::assertInstanceOf(LogicException::class, $bindPlain->unwrapErr());

        $noBindOnErr = Result::err('oops')->bind(fn () => Result::ok(1));
        self::assertTrue($noBindOnErr->isErr());
    }

    public function testTypeErrorAndThrowableInCallbacks(): void
    {
        $mapTypeError = Result::ok(1)->map(function (string $s) {
            return $s;
        });
        self::assertTrue($mapTypeError->isErr());
        self::assertInstanceOf(LogicException::class, $mapTypeError->unwrapErr());

        $bindTypeError = Result::ok(1)->bind(function (string $s) {
            return Result::ok($s);
        });
        self::assertTrue($bindTypeError->isErr());
        self::assertInstanceOf(LogicException::class, $bindTypeError->unwrapErr());

        $bindThrowable = Result::ok(1)->bind(function (int $v) {
            throw new \RuntimeException('boom');
        });
        self::assertTrue($bindThrowable->isErr());
        self::assertInstanceOf(\RuntimeException::class, $bindThrowable->unwrapErr());
    }

    public function testWriterIntegrationAndInspect(): void
    {
        $ok = Result::ok('x')
            ->writeTo('log', 'a')
            ->map(fn (string $s) => strtoupper($s))
            ->writeTo('log', 'b');

        self::assertSame(['a', 'b'], $ok->writerOutput('log'));

        // Writers merge across bind
        $b = Result::ok('x')->writeTo('log', 'a')
            ->bind(function (string $s) {
                return Result::ok($s . 'y')->writeTo('log', 'b');
            });
        self::assertSame(['a', 'b'], $b->writerOutput('log'));

        // inspectOk/inspectErr side effects
        $seen = [];
        Result::ok(5)->inspectOk(function ($v) use (&$seen) { $seen[] = $v; });
        Result::err('bad')->inspectErr(function ($e) use (&$seen) { $seen[] = $e; });
        self::assertCount(2, $seen);
        self::assertSame(5, $seen[0]);
        self::assertInstanceOf(\Throwable::class, $seen[1]);
    }

    public function testWithEnvAndMapWithEnvAndBindWithEnv(): void
    {
        $dep = new class() { public function inc(int $v): int { return $v + 1; } };
        $start = Result::ok(1)->withEnv($dep);
        self::assertTrue($start->isOk());

        // mapWithEnv returns plain value
        $mapped = $start->mapWithEnv([get_class($dep)], function (int $v, array $env) use ($dep) {
            $svc = $env[get_class($dep)];
            return $svc->inc($v);
        });
        self::assertTrue($mapped->isOk());
        self::assertSame(2, $mapped->unwrap());

        // returning Result in mapWithEnv should fail
        $badMapEnv = $start->mapWithEnv([get_class($dep)], function (int $v, array $env) {
            return Result::ok($v);
        });
        self::assertTrue($badMapEnv->isErr());

        // bindWithEnv returns a Result
        $bound = $start->bindWithEnv([get_class($dep)], function (int $v, array $env) use ($dep) {
            $svc = $env[get_class($dep)];
            return Result::ok($svc->inc($v));
        });
        self::assertTrue($bound->isOk());
        self::assertSame(2, $bound->unwrap());

        // missing dependency → Err
        $missing = $start->mapWithEnv([\stdClass::class], fn ($v, $env) => $v);
        self::assertTrue($missing->isErr());
        self::assertInstanceOf(LogicException::class, $missing->unwrapErr());

        $missing2 = $start->bindWithEnv([\stdClass::class], fn ($v, $env) => Result::ok($v));
        self::assertTrue($missing2->isErr());
        self::assertInstanceOf(LogicException::class, $missing2->unwrapErr());
    }

    public function testWithEnvRejectsNonObject(): void
    {
        $this->expectException(\TypeError::class);
        Result::ok(1)->withEnv(1); // invalid argument triggers parameter type error
    }

    public function testApply(): void
    {
        $fnRes = Result::ok(fn (int $v) => $v * 2)->writeTo('log', 'f');
        $valRes = Result::ok(3)->writeTo('log', 'v');

        $applied = $valRes->apply($fnRes);
        self::assertTrue($applied->isOk());
        self::assertSame(6, $applied->unwrap());
        self::assertSame(['v', 'f'], $applied->writerOutput('log'));

        // Non-callable
        $badFnType = $valRes->apply(Result::ok(123));
        self::assertTrue($badFnType->isErr());
        self::assertInstanceOf(LogicException::class, $badFnType->unwrapErr());

        // Callable returns Result -> error
        $badFnReturn = $valRes->apply(Result::ok(fn ($x) => Result::ok($x)));
        self::assertTrue($badFnReturn->isErr());

        // Type error inside callable
        $typeErr = Result::ok('s')->apply(Result::ok(fn (int $i) => $i));
        self::assertTrue($typeErr->isErr());
        self::assertInstanceOf(LogicException::class, $typeErr->unwrapErr());

        // Err short-circuits
        $short1 = Result::err('x')->apply(Result::ok(fn ($v) => $v));
        self::assertTrue($short1->isErr());
        $short2 = Result::ok(1)->apply(Result::err('y'));
        self::assertTrue($short2->isErr());
    }

    public function testApplyWithEnv(): void
    {
        $dep = new class() { public function add(int $v, int $w): int { return $v + $w; } };
        $envRes = Result::ok(10)->withEnv($dep)->writeTo('log', 'v');
        $fnRes = Result::ok(function (int $v, array $env) use ($dep) {
            $svc = $env[get_class($dep)];
            return $svc->add($v, 5);
        })->writeTo('log', 'f');

        $applied = $envRes->applyWithEnv($fnRes, [get_class($dep)]);
        self::assertTrue($applied->isOk());
        self::assertSame(15, $applied->unwrap());
        self::assertSame(['v', 'f'], $applied->writerOutput('log'));

        // Non-callable inside fnResult
        $bad = $envRes->applyWithEnv(Result::ok(123));
        self::assertTrue($bad->isErr());
        self::assertInstanceOf(LogicException::class, $bad->unwrapErr());

        // Callable returns Result -> error
        $badReturn = $envRes->applyWithEnv(Result::ok(fn ($v, $env) => Result::ok($v)));
        self::assertTrue($badReturn->isErr());

        // Missing dependency
        $missing = $envRes->applyWithEnv($fnRes, [\stdClass::class]);
        self::assertTrue($missing->isErr());
        self::assertInstanceOf(LogicException::class, $missing->unwrapErr());

        // Short circuit on errors
        $short1 = Result::err('x')->applyWithEnv($fnRes, []);
        self::assertTrue($short1->isErr());
        $short2 = $envRes->applyWithEnv(Result::err('y'), []);
        self::assertTrue($short2->isErr());
    }
}
