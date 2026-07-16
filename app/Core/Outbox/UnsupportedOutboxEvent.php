<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use RuntimeException;

final class UnsupportedOutboxEvent extends RuntimeException
{
    public function __construct(string $eventType)
    {
        parent::__construct(sprintf('Nessun handler registrato per l’evento outbox "%s".', $eventType));
    }
}
