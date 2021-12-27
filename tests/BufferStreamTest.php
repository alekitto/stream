<?php declare(strict_types=1);

namespace Tests;

use Kcs\Stream\BufferStream;
use Kcs\Stream\Exception\OperationException;
use PHPUnit\Framework\TestCase;

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

    public function testRewindShouldThrow(): void
    {
        $this->expectException(OperationException::class);

        $b = new BufferStream();
        $b->rewind();
    }
}
