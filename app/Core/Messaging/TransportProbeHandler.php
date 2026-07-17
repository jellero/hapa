<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use InvalidArgumentException;

final readonly class TransportProbeHandler implements InboundMessageHandler
{
    public function eventTypes(): array
    {
        return ['integration.transport.probe'];
    }

    public function handle(MessageEnvelope $message): void
    {
        if ($message->schemaVersion !== 1) {
            throw new InvalidArgumentException('Versione non supportata per integration.transport.probe.');
        }

        $probeId = $message->payload['probe_id'] ?? null;
        if (!is_string($probeId) || trim($probeId) === '' || strlen($probeId) > 200) {
            throw new InvalidArgumentException('Il payload del probe richiede probe_id valido.');
        }
    }
}
