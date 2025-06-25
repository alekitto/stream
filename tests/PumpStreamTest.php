<?php

declare(strict_types=1);

namespace Tests;

use Kcs\Stream\BufferStream;
use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\PumpStream;
use PHPUnit\Framework\TestCase;

use const SEEK_CUR;

class PumpStreamTest extends TestCase
{
    public function testCanReadFromCallable(): void
    {
        $p = new PumpStream(static function () {
            return 'a';
        });

        self::assertSame('a', $p->read(1));
        self::assertSame('aaaaa', $p->read(5));
    }

    public function testStoresExcessDataInBuffer(): void
    {
        $called = [];
        $p = new PumpStream(static function ($size) use (&$called) {
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
        $s = new PumpStream(static function () use (&$i) {
            if ($i-- > 0) {
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

    public function testLength(): void
    {
        $a = new PumpStream(static function () {
            static $c = false;
            if ($c) {
                return false;
            }

            $c = true;

            return 'foo';
        }, 3);

        self::assertSame(3, $a->length());

        $a = new PumpStream(static function (): void {
        });
        self::assertNull($a->length());
    }

    public function testPipe(): void
    {
        $a = new PumpStream(static function () {
            static $c = false;
            if ($c) {
                return false;
            }

            $c = true;

            return 'foo';
        });
        $b = new BufferStream();
        $a->pipe($b);

        self::assertSame(3, $b->length());
        self::assertSame('foo', $b->peek(10));
    }

    public function testTell(): void
    {
        $a = new PumpStream(static function (): void {
        });
        self::assertFalse($a->tell());
    }

    public function testSeek(): void
    {
        $a = new PumpStream(static function () {
            static $c = false;
            if ($c) {
                return false;
            }

            $c = true;

            return 'foo';
        });
        self::assertFalse($a->seek(3));

        self::assertTrue($a->seek(1, SEEK_CUR));
        self::assertSame('oo', $a->peek(100));
    }

    public function testCannotBeRewinded(): void
    {
        $this->expectException(OperationException::class);

        $p = new PumpStream(static function (): void {
        });
        $p->rewind();
    }
}
