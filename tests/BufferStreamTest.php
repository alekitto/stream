<?php

declare(strict_types=1);

namespace Tests;

use Kcs\Stream\BufferStream;
use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\ResourceStream;
use PHPUnit\Framework\TestCase;

use function fopen;

use const SEEK_CUR;

class BufferStreamTest extends TestCase
{
    public function testIsADuplex(): void
    {
        $b = new BufferStream();
        self::assertTrue($b->isReadable());
        self::assertTrue($b->isWritable());
        self::assertEquals(0, $b->length());
    }

    public function testRemovesReadDataFromBuffer(): void
    {
        $b = new BufferStream();
        $b->write('foo');
        self::assertEquals(3, $b->length());
        self::assertFalse($b->eof());
        self::assertSame('foo', $b->read(10));
        self::assertEquals(0, $b->length());
        self::assertTrue($b->eof());
        self::assertSame('', $b->read(10));
        self::assertEquals(0, $b->length());
    }

    public function testCanCastToStringOrGetContents(): void
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->write('baz');
        self::assertSame('foo', $b->read(3));
        $b->write('bar');
        self::assertSame('bazbar', (string) $b);
    }

    public function testDetachClearsBuffer(): void
    {
        $b = new BufferStream();
        $b->write('foo');
        $b->close();
        self::assertTrue($b->eof());
        $b->write('abc');
        self::assertSame('abc', $b->read(10));
    }

    public function testPeekBuffer(): void
    {
        $b = new BufferStream();
        $b->write('foo');
        self::assertSame('f', $b->peek(1));
        self::assertSame('fo', $b->peek(2));
        self::assertSame('foo', $b->peek(3));
        self::assertSame('foo', $b->peek(10));
    }

    public function testPipe(): void
    {
        $a = new BufferStream();
        $a->write('foo');
        $b = new BufferStream();
        $a->pipe($b);

        self::assertSame(3, $b->length());
        self::assertSame('foo', $b->peek(10));

        $c = new ResourceStream(fopen('php://temp', 'wb+'));
        $b->pipe($c);

        $c->seek(0);
        self::assertSame('foo', $c->read(10));
    }

    public function testTell(): void
    {
        $a = new BufferStream();
        self::assertFalse($a->tell());
    }

    public function testSeek(): void
    {
        $a = new BufferStream();
        $a->write('foo');
        self::assertFalse($a->seek(3));

        self::assertTrue($a->seek(1, SEEK_CUR));
        self::assertSame('oo', $a->peek(4));
    }

    public function testRewindShouldThrow(): void
    {
        $this->expectException(OperationException::class);

        $b = new BufferStream();
        $b->rewind();
    }
}
