<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use Hapa\Core\Configuration\RabbitMqConsumerConfig;
use InvalidArgumentException;
use JsonException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Hapa\Core\Exception\HapaRuntimeException;
use Throwable;

final class RabbitMqReceiver implements MessageReceiver
{
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;

    public function __construct(private readonly RabbitMqConsumerConfig $configuration)
    {
        if (!$configuration->enabled) {
            throw new HapaRuntimeException('Il consumer RabbitMQ HAPA è disabilitato.');
        }

        $this->connection = new AMQPStreamConnection(
            $configuration->host,
            $configuration->port,
            $configuration->username,
            $configuration->password,
            $configuration->vhost,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $configuration->connectTimeout,
            $configuration->readWriteTimeout,
            null,
            false,
            $configuration->heartbeat,
        );
        $this->channel = $this->connection->channel();
        $this->declareTopology();
    }

    public function consumeOne(callable $consumer): bool
    {
        $delivery = $this->channel->basic_get($this->configuration->queue, false);
        if ($delivery === null) {
            return false;
        }

        try {
            $consumer(MessageEnvelope::fromJson($delivery->getBody()));
            $this->channel->basic_ack($delivery->getDeliveryTag());
        } catch (
            InvalidArgumentException
            | JsonException
            | UnsupportedInboundMessage
            | PermanentInboundFailure $exception
        ) {
            $this->channel->basic_reject($delivery->getDeliveryTag(), false);
            throw $exception;
        } catch (Throwable $exception) {
            $this->channel->basic_nack($delivery->getDeliveryTag(), false, true);
            throw $exception;
        }

        return true;
    }

    public function close(): void
    {
        try {
            if ($this->channel->is_open()) {
                $this->channel->close();
            }
        } catch (Throwable) {
            // Resource cleanup is best effort during shutdown.
        }

        try {
            if ($this->connection->isConnected()) {
                $this->connection->close();
            }
        } catch (Throwable) {
            // Resource cleanup is best effort during shutdown.
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    private function declareTopology(): void
    {
        $this->channel->exchange_declare(
            $this->configuration->exchange,
            'topic',
            false,
            true,
            false,
        );
        $this->channel->exchange_declare(
            $this->configuration->deadExchange,
            'topic',
            false,
            true,
            false,
        );

        $arguments = new AMQPTable([
            'x-dead-letter-exchange' => $this->configuration->deadExchange,
            'x-dead-letter-routing-key' => $this->configuration->deadQueue,
        ]);
        $this->channel->queue_declare(
            $this->configuration->queue,
            false,
            true,
            false,
            false,
            false,
            $arguments,
        );
        foreach ($this->configuration->bindings as $binding) {
            $this->channel->queue_bind(
                $this->configuration->queue,
                $this->configuration->exchange,
                $binding,
            );
        }

        $this->channel->queue_declare(
            $this->configuration->deadQueue,
            false,
            true,
            false,
            false,
        );
        $this->channel->queue_bind(
            $this->configuration->deadQueue,
            $this->configuration->deadExchange,
            $this->configuration->deadQueue,
        );
    }
}
