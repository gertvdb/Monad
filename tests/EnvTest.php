<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Env\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testEmptyAndWithAndAll(): void
    {
        $env = Env::empty();
        self::assertSame([], $env->all());

        $dep = new class() {};
        $env2 = $env->with($dep);
        self::assertNotSame($env, $env2, 'immutable');
        self::assertArrayHasKey(get_class($dep), $env2->all());
        self::assertSame($dep, $env2->get(get_class($dep)));
    }

    public function testReadAndGet(): void
    {
        $dep = new class() {};
        $env = Env::empty()->with($dep);

        self::assertSame($dep, $env->read(get_class($dep)));
        self::assertNull($env->get(\stdClass::class));

        $this->expectException(\LogicException::class);
        $env->read(\stdClass::class);
    }

    public function testLocalAndMerge(): void
    {
        $a = new class() { public int $v = 1; };
        $b = new class() { public int $w = 2; };

        $env1 = new Env([get_class($a) => $a]);
        $env2 = new Env([get_class($b) => $b]);

        $merged = $env1->merge($env2);
        self::assertSame($a, $merged->get(get_class($a)));
        self::assertSame($b, $merged->get(get_class($b)));

        $result = $env1->local(function (Env $e) use ($b) {
            return $e->with($b)->get(get_class($b));
        });
        self::assertSame($b, $result);
    }
}
