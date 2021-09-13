<?php

declare(strict_types=1);

namespace Kcs\Stream;

use Kcs\Stream\Exception\InvalidStreamProvidedException;
use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\Exception\StreamClosedException;

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
use function preg_match;
use function sprintf;
use function stream_get_meta_data;

use const SEEK_SET;

class ResourceStream implements Duplex
{
    private bool $eof;
    private bool $seekable;
    private bool $readable;
    private bool $writable;
    private bool $closed;
    private ?int $fileSize;
    private BufferStream $buffer;

    /** @var resource */
    private $resource;

    /**
     * @param resource $resource
     */
    public function __construct($resource)
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
        $this->resource = $resource;
        $this->closed = false;

        $size = @filesize($metadata['uri']);
        $this->fileSize = $size === false ? null : $size;

        $this->readable = (bool) preg_match('/[r+]/', $metadata['mode']);
        $this->writable = (bool) preg_match('/[wacx+]/', $metadata['mode']);
    }

    public function close(): void
    {
        fclose($this->resource);
        $this->closed = true;
    }

    public function length(): ?int
    {
        return $this->fileSize;
    }

    public function eof(): bool
    {
        return $this->eof;
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
        $content = fread($this->resource, $remaining);
        /** @infection-ignore-all */
        if ($content === false) {
            return $this->buffer->read($length);
        }

        $this->eof = feof($this->resource);

        return $this->buffer->read($length) . $content;
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

        if ($this->seekable === false) {
            $buffer = clone $this->buffer;

            if ($length <= $this->buffer->length()) {
                return $buffer->read($length);
            }

            $remaining = $length - $this->buffer->length();
            $content = fread($this->resource, $remaining);
            if ($content === false) {
                return $buffer->read($length);
            }

            $this->buffer->write($content);
            $buffer = clone $this->buffer;

            return $buffer->read($length);
        }

        $position = ftell($this->resource);
        $content = fread($this->resource, $length);
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
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }
}
