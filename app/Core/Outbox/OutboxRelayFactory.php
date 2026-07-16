<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Configuration\OutboxRelayConfig;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Messaging\MessagePublisher;

final readonly class OutboxRelayFactory
{
    public function __construct(
        private ConnectionFactory $connections,
        private MessagePublisher $publisher,
        private OutboxEnvelopeFactory $envelopes,
        private Clock $clock,
        private OutboxRelayConfig $configuration,
    ) {
    }

    public function create(): OutboxRelay
    {
        return new OutboxRelay(
            new PostgresOutboxRepository($this->connections->create()),
            $this->publisher,
            $this->envelopes,
            $this->clock,
            $this->configuration,
        );
    }
}
