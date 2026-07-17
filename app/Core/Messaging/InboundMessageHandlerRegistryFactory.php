<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use PDO;

interface InboundMessageHandlerRegistryFactory
{
    public function create(PDO $connection): InboundMessageHandlerRegistry;
}
