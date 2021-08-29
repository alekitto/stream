<?php

declare(strict_types=1);

namespace Kcs\Stream;

interface WritableStream extends Stream
{
    /**
     * Write bytes to the stream.
     */
    public function write(string $chunk): void;

    /**
     * Whether the stream is still writable (not closed).
     */
    public function isWritable(): bool;
}
