<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Infrastructure\Messaging;

use Hapa\Core\Configuration\RabbitMqConfig;
use Hapa\Core\Messaging\MessageEnvelope;
use InvalidArgumentException;
use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

final class RabbitMqSpaceCatalogConsumer
{
    private const EXCHANGE = 'hapa.events';
    private const DEAD_EXCHANGE = 'hapa.dead';
    private const QUEUE = 'hapa.space-catalog.observations';
    private const ROUTING_KEY = 'space.catalog.item.observed';

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __construct(private readonly RabbitMqConfig $configuration)
    {
    }

    /** @param callable(MessageEnvelope): void $consumer */
    public function consumeOne(callable $consumer): bool
    {
        $channel = $this->channel();
        $message = $channel->basic_get(self::QUEUE, false);
        if ($message === null) {
            return false;
        }

        try {
            $consumer(MessageEnvelope::fromJson($message->getBody()));
            $channel->basic_ack($message->getDeliveryTag());
        } catch (InvalidArgumentException | JsonException $exception) {
            $channel->basic_reject($message->getDeliveryTag(), false);
            throw $exception;
        } catch (Throwable $exception) {
            $channel->basic_nack($message->getDeliveryTag(), false, true);
            throw $exception;
        }

        return true;
    }

    public function close(): void
    {
        try {
            if ($this->channel !== null && $this->channel->is_open()) {
                $this->channel->close();
            }
        } catch (Throwable) {
        }

        try {
            if ($this->connection !== null && $this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (Throwable) {
        }

        $this->channel = null;
        $this->connection = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open()) {
            return $this->channel;
        }

        $this->connection = new AMQPStreamConnection(
            $this->configuration->host,
            $this->configuration->port,
            $this->configuration->username,
            $this->configuration->password,
            $this->configuration->vhost,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $this->configuration->connectTimeout,
            $this->configuration->readWriteTimeout,
            null,
            false,
            $this->configuration->heartbeat,
        );
        $this->channel = $this->connection->channel();
        $this->channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);
        $this->channel->exchange_declare(self::DEAD_EXCHANGE, 'topic', false, true, false);
        $this->channel->queue_declare(
            self::QUEUE,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable(['x-dead-letter-exchange' => self::DEAD_EXCHANGE]),
        );
        $this->channel->queue_bind(self::QUEUE, self::EXCHANGE, self::ROUTING_KEY);

        return $this->channel;
    }
}
