<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use RuntimeException;

final class LostOutboxLock extends RuntimeException
{
    public function __construct(int $messageId)
    {
        parent::__construct(sprintf('Il lock del messaggio outbox %d non è più valido.', $messageId));
    }
}
