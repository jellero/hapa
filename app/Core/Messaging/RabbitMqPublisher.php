<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use Hapa\Core\Configuration\RabbitMqConfig;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use RuntimeException;
use Throwable;

final class RabbitMqPublisher implements MessagePublisher
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __construct(private readonly RabbitMqConfig $configuration)
    {
    }

    public function publish(string $routingKey, MessageEnvelope $message): void
    {
        if (!$this->configuration->enabled) {
            throw new RuntimeException('Il relay RabbitMQ è disabilitato.');
        }

        if (trim($routingKey) === '') {
            throw new RuntimeException('La routing key RabbitMQ è obbligatoria.');
        }

        $channel = $this->channel();
        $body = $message->toJson();
        $amqpMessage = new AMQPMessage($body, [
            'content_type' => 'application/json',
            'content_encoding' => 'utf-8',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $message->messageId,
            'correlation_id' => $message->correlationId,
            'timestamp' => $message->occurredAt->getTimestamp(),
            'type' => $message->eventType,
            'app_id' => 'hapa',
        ]);

        $channel->basic_publish(
            $amqpMessage,
            $this->configuration->exchange,
            $routingKey,
            false,
            false,
        );
        $channel->wait_for_pending_acks_returns($this->configuration->readWriteTimeout);
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
        $this->channel->exchange_declare(
            $this->configuration->exchange,
            'topic',
            false,
            true,
            false,
        );
        $this->channel->confirm_select();

        return $this->channel;
    }
}
