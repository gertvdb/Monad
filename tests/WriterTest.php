<?php

declare(strict_types=1);

namespace Gertvdb\Monad;

use Gertvdb\Monad\Writer\Writer;
use PHPUnit\Framework\TestCase;

final class WriterTest extends TestCase
{
    public function testEmptyWriteGetAndAll(): void
    {
        $w = Writer::empty();
        self::assertSame([], $w->all());

        $w2 = $w->write('log', 'a')->write('log', 'b')->write('other', 1);
        self::assertNotSame($w, $w2, 'immutable');

        self::assertSame(['a', 'b'], $w2->get('log'));
        self::assertSame([1], $w2->get('other'));
        self::assertSame([], $w2->get('missing'));

        $all = $w2->all();
        self::assertArrayHasKey('log', $all);
        self::assertArrayHasKey('other', $all);
    }

    public function testMerge(): void
    {
        $a = Writer::empty()->write('log', 'a')->write('x', 1);
        $b = Writer::empty()->write('log', 'b')->write('y', 2);

        $merged = $a->merge($b);
        self::assertSame(['a', 'b'], $merged->get('log'));
        self::assertSame([1], $merged->get('x'));
        self::assertSame([2], $merged->get('y'));

        // original unchanged
        self::assertSame(['a'], $a->get('log'));
        self::assertSame(['b'], $b->get('log'));
    }
}
