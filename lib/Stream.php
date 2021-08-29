<?php

declare(strict_types=1);

namespace Kcs\Stream;

interface Stream
{
    /**
     * Closes the stream.
     */
    public function close(): void;
}
