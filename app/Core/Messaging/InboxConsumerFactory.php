<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Configuration\RabbitMqConsumerConfig;
use Hapa\Core\Database\ConnectionFactory;

final readonly class InboxConsumerFactory
{
    public function __construct(
        private ConnectionFactory $connections,
        private InboundMessageHandlerRegistryFactory $handlerRegistries,
        private Clock $clock,
        private RabbitMqConsumerConfig $configuration,
    ) {
    }

    public function create(): InboxConsumer
    {
        $pdo = $this->connections->create();

        return new InboxConsumer(
            $pdo,
            new RabbitMqReceiver($this->configuration),
            new PostgresInboxRepository($pdo),
            $this->handlerRegistries->create($pdo),
            $this->clock,
            $this->configuration,
        );
    }
}
