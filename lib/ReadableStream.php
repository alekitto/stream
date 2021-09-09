<?php

declare(strict_types=1);

namespace Kcs\Stream;

interface ReadableStream extends Stream
{
    /**
     * Whether the stream has been completely read.
     */
    public function eof(): bool;

    /**
     * The total length of the stream (if known).
     */
    public function length(): ?int;

    /**
     * Reads bytes from the stream.
     */
    public function read(int $length): string;

    /**
     * Rewinds the stream.
     */
    public function rewind(): void;

    /**
     * Whether the stream is still readable (not closed).
     */
    public function isReadable(): bool;
}
