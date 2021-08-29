<?php

declare(strict_types=1);

namespace Kcs\Stream\Exception;

use RuntimeException;

class InvalidStreamProvidedException extends RuntimeException implements StreamError
{
}
