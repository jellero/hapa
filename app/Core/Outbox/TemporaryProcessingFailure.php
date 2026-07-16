<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use RuntimeException;

final class TemporaryProcessingFailure extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null)
    {
        if ($retryAfterSeconds !== null && $retryAfterSeconds < 1) {
            throw new RuntimeException('Il ritardo retry esplicito deve essere positivo.');
        }

        parent::__construct($message);
    }
}
