<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use InvalidArgumentException;

final readonly class RabbitMqConfig
{
    public function __construct(
        public bool $enabled,
        public string $host,
        public int $port,
        public string $vhost,
        public string $username,
        public string $password,
        public string $exchange,
        public float $connectTimeout,
        public float $readWriteTimeout,
        public int $heartbeat,
    ) {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('La porta RabbitMQ non è valida.');
        }

        if ($connectTimeout <= 0 || $readWriteTimeout <= 0 || $heartbeat < 0) {
            throw new InvalidArgumentException('Timeout e heartbeat RabbitMQ non sono validi.');
        }

        foreach (['host' => $host, 'vhost' => $vhost, 'exchange' => $exchange] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Il campo RabbitMQ %s è obbligatorio.', $field));
            }
        }

        if ($enabled && (trim($username) === '' || $password === '')) {
            throw new InvalidArgumentException('Credenziali RabbitMQ obbligatorie quando il relay è abilitato.');
        }
    }
}
