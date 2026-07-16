<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use InvalidArgumentException;

final readonly class OutboxRelayConfig
{
    public function __construct(
        public string $workerId,
        public int $batchSize,
        public int $lockTimeoutSeconds,
        public int $retryBaseSeconds,
        public int $retryMaximumSeconds,
    ) {
        if (trim($workerId) === '') {
            throw new InvalidArgumentException('L’identità del relay outbox è obbligatoria.');
        }

        if ($batchSize < 1 || $batchSize > 500) {
            throw new InvalidArgumentException('La dimensione batch del relay deve essere compresa tra 1 e 500.');
        }

        if ($lockTimeoutSeconds < 30) {
            throw new InvalidArgumentException('Il timeout lock del relay deve essere almeno 30 secondi.');
        }

        if ($retryBaseSeconds < 1 || $retryMaximumSeconds < $retryBaseSeconds) {
            throw new InvalidArgumentException('La configurazione retry del relay non è valida.');
        }
    }
}
