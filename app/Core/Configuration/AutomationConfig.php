<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use InvalidArgumentException;

final readonly class AutomationConfig
{
    public function __construct(
        public int $batchSize,
        public int $lockTimeoutSeconds,
        public int $retryBaseSeconds,
        public int $retryMaximumSeconds,
    ) {
        if ($batchSize < 1 || $batchSize > 500) {
            throw new InvalidArgumentException('AUTOMATION_BATCH_SIZE deve essere compreso tra 1 e 500.');
        }

        if ($lockTimeoutSeconds < 30 || $lockTimeoutSeconds > 86400) {
            throw new InvalidArgumentException('AUTOMATION_LOCK_TIMEOUT deve essere compreso tra 30 e 86400 secondi.');
        }

        if ($retryBaseSeconds < 1 || $retryMaximumSeconds < $retryBaseSeconds) {
            throw new InvalidArgumentException('I ritardi retry delle automazioni non sono coerenti.');
        }
    }
}
