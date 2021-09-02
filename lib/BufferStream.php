<?php

declare(strict_types=1);

namespace Kcs\Stream;

use Kcs\Stream\Exception\OperationException;

use function strlen;
use function substr;

/**
 * Provides a buffer stream that can be written to fill a buffer, and read
 * from to remove bytes from the buffer.
 */
final class BufferStream implements Duplex
{
    private string $buffer = '';

    public function __toString(): string
    {
        $buffer = $this->buffer;
        $this->buffer = '';

        return $buffer;
    }

    public function close(): void
    {
        $this->buffer = '';
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function rewind(): void
    {
        throw new OperationException('Cannot seek a BufferStream');
    }

    public function eof(): bool
    {
        return $this->buffer === '';
    }

    /**
     * Reads data from the buffer.
     */
    public function read(int $length): string
    {
        $currentLength = strlen($this->buffer);

        if ($length >= $currentLength) {
            // No need to slice the buffer because we don't have enough data.
            $result = $this->buffer;
            $this->buffer = '';
        } else {
            // Slice up the result to provide a subset of the buffer.
            $result = substr($this->buffer, 0, $length);
            $this->buffer = substr($this->buffer, $length);
        }

        return $result;
    }

    /**
     * Writes data to the buffer.
     */
    public function write(string $chunk): void
    {
        $this->buffer .= $chunk;
    }
}
