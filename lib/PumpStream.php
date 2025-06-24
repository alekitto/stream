<?php

declare(strict_types=1);

namespace Kcs\Stream;

use Closure;
use Kcs\Stream\Exception\OperationException;

use function call_user_func;
use function strlen;

use const SEEK_CUR;
use const SEEK_SET;

/**
 * Provides a read only stream that pumps data from a PHP callable.
 *
 * When invoking the provided callable, the PumpStream will pass the amount of
 * data requested to read to the callable. The callable can choose to ignore
 * this value and return fewer or more bytes than requested. Any extra data
 * returned by the provided callable is buffered internally until drained using
 * the read() function of the PumpStream. The provided callable MUST return
 * false when there is no more data to read.
 */
class PumpStream implements ReadableStream
{
    private Closure|null $source;
    private BufferStream $buffer;

    /**
     * @param callable $source Source of the stream data. The callable MAY
     *                         accept an integer argument used to control the
     *                         amount of data to return. The callable MUST
     *                         return a string when called, or false on error
     *                         or EOF.
     * @param int|null $length Set the total length of the stream, if known in advance.
     */
    public function __construct(callable $source, private int|null $length = null)
    {
        $this->source = $source(...);
        $this->buffer = new BufferStream();
    }

    public function __toString(): string
    {
        $result = '';
        while (! $this->eof()) {
            /** @infection-ignore-all */
            $result .= $this->read(1000000);
        }

        return $result;
    }

    public function close(): void
    {
        $this->source = null;
    }

    public function length(): int|null
    {
        return $this->length;
    }

    public function eof(): bool
    {
        return ! isset($this->source) && $this->buffer->eof();
    }

    public function rewind(): void
    {
        throw new OperationException('Cannot seek a PumpStream');
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $data = $this->buffer->read($length);
        $readLen = strlen($data);
        $remaining = $length - $readLen;

        if ($remaining) {
            $this->pump($remaining);
            $data .= $this->buffer->read($remaining);
        }

        return $data;
    }

    public function pipe(WritableStream $destination): void
    {
        while (! $this->eof()) {
            $destination->write($this->read(4096));
        }
    }

    public function peek(int $length): string
    {
        $data = $this->buffer->peek($length);
        $readLen = strlen($data);
        $remaining = $length - $readLen;

        if ($remaining) {
            $this->pump($remaining);
            $data .= $this->buffer->peek($remaining);
        }

        return $data;
    }

    public function tell(): int|false
    {
        return false;
    }

    public function seek(int $position, int $whence = SEEK_SET): bool
    {
        return $whence === SEEK_CUR && $this->read($position);
    }

    private function pump(int $length): void
    {
        if (! isset($this->source)) {
            return;
        }

        do {
            $data = call_user_func($this->source, $length);
            if ($data === false || $data === null) {
                unset($this->source);

                return;
            }

            $this->buffer->write($data);
            $length -= strlen($data);
        } while ($length > 0);
    }
}
