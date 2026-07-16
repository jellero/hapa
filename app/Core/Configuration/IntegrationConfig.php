<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use RuntimeException;

final readonly class IntegrationConfig
{
    public function __construct(
        public float $connectTimeout,
        public float $requestTimeout,
        public int $maximumResponseBytes,
    ) {
        if ($connectTimeout <= 0 || $connectTimeout > 30) {
            throw new RuntimeException('INTEGRATION_CONNECT_TIMEOUT non valido.');
        }

        if ($requestTimeout < $connectTimeout || $requestTimeout > 120) {
            throw new RuntimeException('INTEGRATION_REQUEST_TIMEOUT deve includere il timeout di connessione e non superare 120 secondi.');
        }

        if ($maximumResponseBytes < 1024 || $maximumResponseBytes > 50 * 1024 * 1024) {
            throw new RuntimeException('INTEGRATION_MAX_RESPONSE_BYTES deve essere compreso tra 1 KiB e 50 MiB.');
        }
    }
}
