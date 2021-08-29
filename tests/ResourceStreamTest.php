<?php

declare(strict_types=1);

namespace Tests;

use Kcs\Stream\Exception\InvalidStreamProvidedException;
use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\Exception\StreamClosedException;
use Kcs\Stream\ResourceStream;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\Fixtures\TestHttpServer;

use function curl_init;
use function Safe\fopen;
use function sys_get_temp_dir;

class ResourceStreamTest extends TestCase
{
    public function testShouldThrowIfNotAResource(): void
    {
        $this->expectException(InvalidStreamProvidedException::class);
        $this->expectExceptionMessage('Invalid stream provided: expected stream resource, received stdClass');

        new ResourceStream(new stdClass());
    }

    /**
     * @requires PHP < 8.0
     */
    public function testShouldThrowIfNotAStreamResource(): void
    {
        $this->expectException(InvalidStreamProvidedException::class);
        $this->expectExceptionMessage('Invalid stream provided: expected stream resource, received resource of type curl');

        new ResourceStream(curl_init('http://localhost'));
    }

    public function testShouldAcceptAReadableStream(): void
    {
        $stream = new ResourceStream(fopen('data://text/plain,foobar', 'rb'));

        self::assertTrue($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->eof());
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
}
