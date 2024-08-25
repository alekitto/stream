<?php

declare(strict_types=1);

namespace Kcs\Stream;

use Kcs\Stream\Exception\InvalidStreamProvidedException;
use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\Exception\StreamClosedException;

use function assert;
use function fclose;
use function feof;
use function filesize;
use function fread;
use function fseek;
use function ftell;
use function fwrite;
use function get_debug_type;
use function get_resource_type;
use function is_resource;
use function min;
use function preg_match;
use function sprintf;
use function stream_copy_to_stream;
use function stream_get_meta_data;

use const SEEK_SET;

class ResourceStream implements Duplex
{
    private bool $eof;
    private bool $seekable;
    private bool $readable;
    private bool $writable;
    private bool $closed;
    private int|null $fileSize;
    private BufferStream $buffer;

    /** @param resource $resource */
    public function __construct(private $resource)
    {
        if (! is_resource($resource)) {
            throw new InvalidStreamProvidedException(sprintf('Invalid stream provided: expected stream resource, received %s', get_debug_type($resource)));
        }

        if (get_resource_type($resource) !== 'stream') {
            throw new InvalidStreamProvidedException(sprintf('Invalid stream provided: expected stream resource, received resource of type %s', get_resource_type($resource)));
        }

        $this->buffer = new BufferStream();
        $metadata = stream_get_meta_data($resource);
        $this->eof = feof($resource);
        $this->seekable = $metadata['seekable'];
        $this->closed = false;

        $uri = $metadata['uri'] ?? '';
        $size = @filesize($uri);
        $this->fileSize = $size === false ? null : $size;

        $this->readable = (bool) preg_match('/[r+]/', $metadata['mode']);
        $this->writable = (bool) preg_match('/[wacx+]/', $metadata['mode']);
    }

    public function close(): void
    {
        fclose($this->resource);
        $this->closed = true;
    }

    public function length(): int|null
    {
        return $this->fileSize;
    }

    public function eof(): bool
    {
        return $this->buffer->eof() && $this->eof;
    }

    public function read(int $length): string
    {
        if ($this->closed) {
            throw new StreamClosedException('Trying to read a closed stream');
        }

        if (! $this->readable) {
            throw new OperationException('Trying to read from a write-only stream');
        }

        if ($length <= 0) {
            return '';
        }

        if ($length <= $this->buffer->length()) {
            return $this->buffer->read($length);
        }

        $remaining = $length - $this->buffer->length();

        $content = '';
        for (; $remaining > 0 && ! $this->eof(); $remaining -= 4096) {
            $bytes = min($remaining, 4096);
            $chunk = fread($this->resource, $bytes);
            $this->eof = feof($this->resource);

            /** @infection-ignore-all */
            if ($chunk === false) {
                break;
            }

            $content .= $chunk;
        }

        return $this->buffer->read($length) . $content;
    }

    public function pipe(WritableStream $destination): void
    {
        if ($destination instanceof self) {
            $result = stream_copy_to_stream($this->resource, $destination->resource);
            if ($result === false) {
                throw new OperationException('Failed to copy stream');
            }

            return;
        }

        while (! $this->eof()) {
            $destination->write($this->read(4096));
        }
    }

    public function peek(int $length): string
    {
        if ($this->closed) {
            throw new StreamClosedException('Trying to read a closed stream');
        }

        if (! $this->readable) {
            throw new OperationException('Trying to read from a write-only stream');
        }

        if ($length <= 0) {
            return '';
        }

        $position = ftell($this->resource);
        if ($this->seekable === false || $position === false) {
            if ($length <= $this->buffer->length()) {
                return $this->buffer->peek($length);
            }

            $remaining = $length - $this->buffer->length();
            assert($remaining > 0);

            $content = fread($this->resource, $remaining);
            if ($content === false) {
                return $this->buffer->peek($length);
            }

            $this->buffer->write($content);

            return $this->buffer->peek($length);
        }

        $length = $this->fileSize !== null && $position + $length >= $this->fileSize ? $this->fileSize - $position : $length;
        $content = $length > 0 ? fread($this->resource, $length) : false;
        /** @infection-ignore-all */
        if ($content === false) {
            return '';
        }

        fseek($this->resource, $position);

        return $content;
    }

    public function rewind(): void
    {
        if ($this->seekable === false) {
            return;
        }

        fseek($this->resource, 0, SEEK_SET);
        $this->eof = feof($this->resource);
        $this->buffer = new BufferStream();
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    public function write(string $chunk): void
    {
        if ($this->closed) {
            throw new StreamClosedException('Trying to write on a closed stream');
        }

        if (! $this->writable) {
            throw new OperationException('Trying to write to a read-only stream');
        }

        fwrite($this->resource, $chunk);
        $this->eof = feof($this->resource);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }
}
