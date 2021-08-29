<?php

declare(strict_types=1);

namespace Kcs\Stream;

use Kcs\Stream\Exception\InvalidStreamProvidedException;
use Kcs\Stream\Exception\OperationException;
use Kcs\Stream\Exception\StreamClosedException;

use function fclose;
use function feof;
use function fread;
use function fseek;
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

        $metadata = stream_get_meta_data($resource);
        $this->eof = feof($resource);
        $this->seekable = $metadata['seekable'];
        $this->resource = $resource;
        $this->closed = false;

        $this->readable = (bool) preg_match('/[r+]/', $metadata['mode']);
        $this->writable = (bool) preg_match('/[wacx+]/', $metadata['mode']);
    }

    public function close(): void
    {
        fclose($this->resource);
        $this->closed = true;
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

        $content = fread($this->resource, $length);
        /** @infection-ignore-all */
        if ($content === false) {
            return '';
        }

        $this->eof = feof($this->resource);

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
