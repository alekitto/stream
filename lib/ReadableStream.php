<?php

declare(strict_types=1);

namespace Kcs\Stream;

use const SEEK_SET;

interface ReadableStream extends Stream
{
    /**
     * Whether the stream has been completely read.
     */
    public function eof(): bool;

    /**
     * The total length of the stream (if known).
     */
    public function length(): int|null;

    /**
     * Reads bytes from the stream.
     */
    public function read(int $length): string;

    /**
     * Pipes the current stream into another (writable) stream.
     */
    public function pipe(WritableStream $destination): void;

    /**
     * Reads bytes from the stream but do not advance stream pointer.
     */
    public function peek(int $length): string;

    /**
     * If supported, returns the current position in the stream.
     */
    public function tell(): int|false;

    /**
     * If supported, move the current position of the stream to $offset
     */
    public function seek(int $position, int $whence = SEEK_SET): bool;

    /**
     * Rewinds the stream.
     */
    public function rewind(): void;

    /**
     * Whether the stream is still readable (not closed).
     */
    public function isReadable(): bool;
}
