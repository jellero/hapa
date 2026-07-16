<?php

declare(strict_types=1);

namespace Hapa\Core\Messaging;

use Hapa\Core\Clock\Clock;
use Hapa\Core\Configuration\RabbitMqConsumerConfig;
use InvalidArgumentException;
use PDO;
use Throwable;

final readonly class InboxConsumer
{
    public function __construct(
        private PDO $pdo,
        private MessageReceiver $receiver,
        private InboxRepository $inbox,
        private InboundMessageHandlerRegistry $handlers,
        private Clock $clock,
        private RabbitMqConsumerConfig $configuration,
    ) {
    }

    public function runOnce(): InboxConsumerReport
    {
        $processed = false;
        $duplicate = false;
        $consumed = $this->receiver->consumeOne(function (MessageEnvelope $message) use (
            &$processed,
            &$duplicate,
        ): void {
            $attempt = null;

            try {
                $this->pdo->beginTransaction();
                $attempt = $this->inbox->begin($message, $this->clock->now());
                if ($attempt === null) {
                    $this->pdo->commit();
                    $duplicate = true;

                    return;
                }

                $this->handlers->handle($message);
                $this->inbox->complete($message->messageId, $this->clock->now());
                $this->pdo->commit();
                $processed = true;
            } catch (Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                if ($attempt !== null) {
                    try {
                        $this->pdo->beginTransaction();
                        $this->inbox->recordFailure(
                            $message,
                            $attempt,
                            $this->clock->now(),
                            $exception->getMessage(),
                        );
                        $this->pdo->commit();
                    } catch (Throwable $recordingFailure) {
                        if ($this->pdo->inTransaction()) {
                            $this->pdo->rollBack();
                        }

                        throw $recordingFailure;
                    }
                }

                if (
                    $exception instanceof InvalidArgumentException
                    || $exception instanceof UnsupportedInboundMessage
                    || $exception instanceof PermanentInboundFailure
                    || ($attempt !== null && $attempt >= $this->configuration->maximumAttempts)
                ) {
                    throw new PermanentInboundFailure(
                        sprintf('Messaggio inbound %s rifiutato definitivamente.', $message->messageId),
                        $exception,
                    );
                }

                throw $exception;
            }
        });

        return new InboxConsumerReport($consumed, $processed, $duplicate);
    }
}
