<?php declare(strict_types=1);

namespace Tests;


use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\PumpStream;
use PHPUnit\Framework\TestCase;

class PumpStreamTest extends TestCase
{
    public function testCanReadFromCallable(): void
    {
        $p = new PumpStream(function () {
            return 'a';
        });

        self::assertSame('a', $p->read(1));
        self::assertSame('aaaaa', $p->read(5));
    }

    public function testStoresExcessDataInBuffer(): void
    {
        $called = [];
        $p = new PumpStream(function ($size) use (&$called) {
            $called[] = $size;

            return 'abcdef';
        });

        self::assertSame('a', $p->read(1));
        self::assertSame('b', $p->read(1));
        self::assertSame('cde', $p->read(3));
        self::assertSame('fabcdefabc', $p->read(10));
        self::assertSame([1, 9, 3], $called);
    }

    public function testInfiniteStreamWrappedInLimitStream(): void
    {
        $i = 5;
        $s = new PumpStream(function () use (&$i) {
            if ($i --> 0) {
                return 'a';
            }

            return false;
        });

        self::assertSame('aaaaa', (string) $s);
    }

    public function testDescribesCapabilities(): void
    {
        $p = new PumpStream(static function (): void {
        });
        self::assertTrue($p->isReadable());
        self::assertSame('', (string) $p);
        $p->close();
        self::assertSame('', $p->read(10));
        self::assertTrue($p->eof());
    }

    public function testCannotBeRewinded(): void
    {
        $this->expectException(OperationException::class);

        $p = new PumpStream(static function (): void {
        });
        $p->rewind();
    }
}
