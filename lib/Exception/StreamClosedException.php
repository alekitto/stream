<?php

declare(strict_types=1);

namespace Kcs\Stream\Exception;

use RuntimeException;

class StreamClosedException extends RuntimeException implements StreamError
{
}
