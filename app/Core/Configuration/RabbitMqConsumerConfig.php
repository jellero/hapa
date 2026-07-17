<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use InvalidArgumentException;

final readonly class RabbitMqConsumerConfig
{
    /** @param list<string> $bindings */
    public function __construct(
        public bool $enabled,
        public string $host,
        public int $port,
        public string $vhost,
        public string $username,
        public string $password,
        public string $exchange,
        public string $deadExchange,
        public string $queue,
        public string $deadQueue,
        public array $bindings,
        public float $connectTimeout,
        public float $readWriteTimeout,
        public int $heartbeat,
        public int $maximumAttempts,
    ) {
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('La porta RabbitMQ consumer non è valida.');
        }

        if ($connectTimeout <= 0 || $readWriteTimeout <= 0 || $heartbeat < 0) {
            throw new InvalidArgumentException('Timeout e heartbeat RabbitMQ consumer non sono validi.');
        }

        if ($maximumAttempts < 1 || $maximumAttempts > 100) {
            throw new InvalidArgumentException('Il limite tentativi del consumer deve essere compreso tra 1 e 100.');
        }

        foreach ([
            'host' => $host,
            'vhost' => $vhost,
            'exchange' => $exchange,
            'dead exchange' => $deadExchange,
            'queue' => $queue,
            'dead queue' => $deadQueue,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException(sprintf('Il campo RabbitMQ consumer %s è obbligatorio.', $field));
            }
        }

        foreach ($bindings as $binding) {
            if (trim($binding) === '') {
                throw new InvalidArgumentException('Le binding key RabbitMQ consumer non possono essere vuote.');
            }
        }

        if ($enabled && (trim($username) === '' || $password === '' || $bindings === [])) {
            throw new InvalidArgumentException(
                'Credenziali e almeno una binding key sono obbligatorie quando il consumer è abilitato.',
            );
        }
    }
}
