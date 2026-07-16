<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use RuntimeException;
use Throwable;

final class PermanentInboundFailure extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
