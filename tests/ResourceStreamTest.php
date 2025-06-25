<?php

declare(strict_types=1);

namespace Tests;

use Kcs\Stream\BufferStream;
use Kcs\Stream\Exception\InvalidStreamProvidedException;
use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\Exception\StreamClosedException;
use Kcs\Stream\ResourceStream;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Fixtures\TestHttpServer;

use function fseek;
use function fwrite;
use function openssl_random_pseudo_bytes;
use function proc_open;
use function Safe\fopen;
use function sys_get_temp_dir;

use const PHP_INT_MAX;
use const PHP_OS;
use const SEEK_SET;

class ResourceStreamTest extends TestCase
{
    public function testShouldThrowIfNotAResource(): void
    {
        $this->expectException(InvalidStreamProvidedException::class);
        $this->expectExceptionMessage('Invalid stream provided: expected stream resource, received stdClass');

        new ResourceStream(new stdClass());
    }

    public function testShouldThrowIfNotAStreamResource(): void
    {
        $this->expectException(InvalidStreamProvidedException::class);
        $this->expectExceptionMessage('Invalid stream provided: expected stream resource, received resource of type process');

        new ResourceStream(proc_open(PHP_OS === 'Windows' ? 'cmd.exe' : '/bin/bash', [], $pipes));
    }

    public function testShouldAcceptAReadableStream(): void
    {
        $stream = new ResourceStream(fopen('data://text/plain,foobar', 'rb'));

        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->eof());
    }

    public function testShouldReturnFileLength(): void
    {
        $stream = new ResourceStream(fopen('data://text/plain,foobar', 'rb'));
        self::assertNull($stream->length());

        $stream = new ResourceStream(fopen(__FILE__, 'rb'));
        self::assertNotNull($stream->length());
    }

    public function testShouldAcceptAWritableStream(): void
    {
        $stream = new ResourceStream(fopen('php://temp', 'ab+'));

        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertFalse($stream->eof());
    }

    public function testShouldReadFromStream(): void
    {
        $stream = new ResourceStream(fopen('data://text/plain,foobar', 'rb'));

        self::assertEquals('', $stream->read(0));
        self::assertEquals('foo', $stream->read(3));
        self::assertEquals('bar', $stream->read(3));
        self::assertEquals('', $stream->read(1));
        self::assertTrue($stream->eof());
    }

    public function testShouldPeek(): void
    {
        $stream = new ResourceStream(fopen('data://text/plain,foobar', 'rb'));

        self::assertEquals('', $stream->peek(0));
        self::assertEquals('foo', $stream->peek(3));
        self::assertEquals('foobar', $stream->peek(6));
        self::assertEquals('foo', $stream->read(3));
    }

    public function testPeekShouldThrowOnClosedStream(): void
    {
        $this->expectException(StreamClosedException::class);
        $this->expectExceptionMessage('Trying to read a closed stream');

        $stream = new ResourceStream(fopen('data://text/plain,foobar', 'rb'));
        $stream->close();

        $stream->peek(1);
    }

    public function testPeekShouldThrowOnWriteOnlyStream(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('Trying to read from a write-only stream');

        $stream = new ResourceStream(fopen(sys_get_temp_dir() . '/test_file', 'cb'));
        $stream->peek(1);
    }

    public function testShouldNotThrowTryingToRewindANonSeekableStream(): void
    {
        TestHttpServer::start();
        $stream = new ResourceStream(fopen('http://localhost:8057/', 'rb'));

        self::assertEquals('', $stream->read(0));
        self::assertEquals("{\n    \"SER", $stream->read(10));

        $stream->rewind();
        self::assertEquals('VER_PROTOC', $stream->read(10));
    }

    public function testShouldWriteStream(): void
    {
        $stream = new ResourceStream(fopen('php://temp', 'rb+'));

        $stream->write('foobar');

        $stream->rewind();
        self::assertEquals('foobar', $stream->read(100));
        $stream->rewind();
        self::assertEquals('foobar', $stream->read(100));
    }

    public function testShouldThrowTryingToWriteToAReadOnlyStream(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('Trying to write to a read-only stream');

        $stream = new ResourceStream(fopen('data://text/plain,foobar', 'rb'));
        $stream->write('test');
    }

    public function testShouldThrowTryingToWriteToAClosedStream(): void
    {
        $this->expectException(StreamClosedException::class);
        $this->expectExceptionMessage('Trying to write on a closed stream');

        $stream = new ResourceStream(fopen('php://temp', 'rb+'));
        $stream->close();
        $stream->write('test');
    }

    public function testShouldThrowTryingToReadFromAWriteOnlyStream(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('Trying to read from a write-only stream');

        $stream = new ResourceStream(fopen(sys_get_temp_dir() . '/test_file', 'cb'));
        $stream->read(1);
    }

    public function testShouldThrowTryingToReadFromAClosedStream(): void
    {
        $this->expectException(StreamClosedException::class);
        $this->expectExceptionMessage('Trying to read a closed stream');

        $stream = new ResourceStream(fopen('php://temp', 'rb+'));
        $stream->close();
        $stream->read(1);
    }

    public function testCouldReadLongStreamInChunks(): void
    {
        $bytes = openssl_random_pseudo_bytes(6 * 1024);
        if ($bytes === false) {
            self::markTestSkipped('Cannot execute this test');
        }

        $r = fopen('php://temp', 'rb+');
        fwrite($r, $bytes);
        fseek($r, 0, SEEK_SET);

        $stream = new ResourceStream($r);
        self::assertEquals($bytes, $stream->read(PHP_INT_MAX));
    }

    public function testShouldSupportPeekOnNonSeekableStreams(): void
    {
        TestHttpServer::start();
        $stream = new ResourceStream(fopen('http://localhost:8057/', 'rb'));

        self::assertEquals('', $stream->peek(0));
        self::assertEquals("{\n    \"SER", $stream->peek(10));

        $stream->rewind();
        self::assertEquals("{\n    ", $stream->read(6));
        self::assertEquals('"SERVER_PROTOCOL": "', $stream->read(20));

        // Read to end
        $stream->read(PHP_INT_MAX);
    }

    public function testShouldSupportPipeToSelf(): void
    {
        TestHttpServer::start();
        $stream = new ResourceStream(fopen('http://localhost:8057/', 'rb'));

        $a = new ResourceStream(fopen('php://temp', 'rb+'));
        $stream->pipe($a);

        self::assertEquals("{\n", $a->peek(2));
    }

    public function testPipeShouldThrowIfOtherStreamIsNotWritable(): void
    {
        $this->expectException(OperationException::class);
        $this->expectExceptionMessage('Trying to write to a read-only stream');

        TestHttpServer::start();
        $stream = new ResourceStream(fopen('http://localhost:8057/', 'rb'));

        $a = new ResourceStream(fopen('php://temp', 'rb'));
        $stream->pipe($a);
    }

    public function testPipeShouldThrowIfOtherStreamIsClosed(): void
    {
        $this->expectException(StreamClosedException::class);
        $this->expectExceptionMessage('Trying to write to a closed stream');

        TestHttpServer::start();
        $stream = new ResourceStream(fopen('http://localhost:8057/', 'rb'));

        $a = new ResourceStream(fopen('php://temp', 'rb'));
        $a->close();
        $stream->pipe($a);
    }

    public function testShouldSupportPipeToOtherStreams(): void
    {
        TestHttpServer::start();
        $stream = new ResourceStream(fopen('http://localhost:8057/', 'rb'));

        $a = new BufferStream();
        $stream->pipe($a);

        self::assertGreaterThan(0, $a->length());
        self::assertEquals("{\n", $a->peek(2));
    }

    public function testTellShouldThrowIfOtherStreamIsClosed(): void
    {
        $this->expectException(StreamClosedException::class);
        $this->expectExceptionMessage('Trying to query a closed stream');

        $a = new ResourceStream(fopen('php://temp', 'rb'));
        $a->close();
        $a->tell();
    }

    public function testSeekShouldThrowIfOtherStreamIsClosed(): void
    {
        $this->expectException(StreamClosedException::class);
        $this->expectExceptionMessage('Trying to seek a closed stream');

        $a = new ResourceStream(fopen('php://temp', 'rb'));
        $a->close();
        self::assertTrue($a->seek(0));
    }

    public function testSeekShouldReturnFalseIfPositionIsLessThenZero(): void
    {
        $a = new ResourceStream(fopen('php://temp', 'rb'));
        self::assertFalse($a->seek(-1));
    }
}
