<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use PHPUnit\Framework\TestCase;

final class ResultListTest extends TestCase
{
    public function testEmptyAddAndOfConstructors(): void
    {
        $list = ResultList::empty();
        self::assertTrue($list->isOk());
        self::assertCount(0, $list);

        $list = $list->add(1)->add(Result::ok(2))->add(Result::err('x'));
        self::assertTrue($list->isErr(), 'any Err inside makes the list Err');
        self::assertCount(3, $list);

        $list2 = ResultList::of([1, Result::ok(2), Result::err('y')]);
        self::assertTrue($list2->isErr());
        self::assertCount(3, $list2);
    }

    public function testIsOkAndIsErrPredicates(): void
    {
        $okOnly = ResultList::of([Result::ok(1), 2, 3]);
        self::assertTrue($okOnly->isOk());
        self::assertFalse($okOnly->isErr());

        $mixed = ResultList::of([1, Result::err('bad'), 3]);
        self::assertFalse($mixed->isOk());
        self::assertTrue($mixed->isErr());
    }

    public function testMapOverItems(): void
    {
        $start = ResultList::of([1, 2, 3]);
        $mapped = $start->map(fn (int $v) => $v * 2);
        self::assertTrue($mapped->isOk());
        self::assertSame([2, 4, 6], $mapped->unwrap());

        // Errors pass through
        $withErr = ResultList::of([1, Result::err('x'), 3]);
        $mapped2 = $withErr->map(fn (int $v) => $v + 1);
        self::assertTrue($mapped2->isErr());
        // filterOk keeps only Ok items
        $oks = $mapped2->filterOk();
        self::assertTrue($oks->isOk());
        self::assertSame([2, 4], $oks->unwrap());
    }

    public function testBindExpectsResultPerItem(): void
    {
        $start = ResultList::of([1, 2, 3]);
        $bound = $start->bind(fn (int $v) => Result::ok($v + 1));
        self::assertTrue($bound->isOk());
        self::assertSame([2, 3, 4], $bound->unwrap());

        // Existing errors stay
        $withErr = ResultList::of([1, Result::err('oops'), 3]);
        $bound2 = $withErr->bind(fn (int $v) => Result::ok($v * 10));
        self::assertTrue($bound2->isErr());
        $oks = $bound2->filterOk();
        self::assertSame([10, 30], $oks->unwrap());
    }

    public function testBindWithEnvAndMapWithEnv(): void
    {
        $dep = new class() { public function inc(int $v): int { return $v + 1; } };
        $cls = get_class($dep);

        $start = ResultList::of([1, 2])->withEnv($dep);

        $mapped = $start->mapWithEnv([$cls], function (int $v, array $env) use ($cls) {
            $svc = $env[$cls];
            return $svc->inc($v);
        });
        self::assertTrue($mapped->isOk());
        self::assertSame([2, 3], $mapped->unwrap());

        $bound = $start->bindWithEnv([$cls], function (int $v, array $env) use ($cls) {
            $svc = $env[$cls];
            return Result::ok($svc->inc($v));
        });
        self::assertTrue($bound->isOk());
        self::assertSame([2, 3], $bound->unwrap());

        // Missing dependency yields an Err list
        $missingMap = $start->mapWithEnv([\stdClass::class], fn ($v, $env) => $v);
        self::assertTrue($missingMap->isErr());

        $missingBind = $start->bindWithEnv([\stdClass::class], fn ($v, $env) => Result::ok($v));
        self::assertTrue($missingBind->isErr());
    }

    public function testWriterAndInspect(): void
    {
        $a = Result::ok('a')->writeTo('log', 'ia');
        $b = Result::ok('b')->writeTo('log', 'ib');
        $list = ResultList::of([$a, $b])->writeTo('log', 'L');

        // Writer collects from items and list itself
        self::assertSame(['ia', 'ib', 'L'], $list->writerOutput('log'));

        // inspectOk/inspectErr delegates to items
        $seen = [];
        $err = Result::err('bad');
        $list2 = ResultList::of([$a, $err]);
        $list2->inspectOk(function ($v) use (&$seen) { $seen[] = $v; })
              ->inspectErr(function ($e) use (&$seen) { $seen[] = $e; });
        self::assertCount(2, $seen);
        self::assertSame('a', $seen[0]);
        self::assertInstanceOf(\Throwable::class, $seen[1]);
    }

    public function testUnwrapAndIterationAndCount(): void
    {
        $list = ResultList::of([1, 2, 3]);
        self::assertSame([1, 2, 3], $list->unwrap());

        $collected = [];
        foreach ($list as $res) {
            self::assertInstanceOf(Result::class, $res);
            if ($res->isOk()) {
                $collected[] = $res->unwrap();
            }
        }
        self::assertSame([1, 2, 3], $collected);
        self::assertCount(3, $list);

        $this->expectException(\Throwable::class);
        ResultList::of([1, Result::err('x')])->unwrap();
    }

    public function testWithEnvRejectsNonObject(): void
    {
        $this->expectException(\TypeError::class);
        // invalid argument triggers parameter type error
        ResultList::of([1])->withEnv(1);
    }
}
