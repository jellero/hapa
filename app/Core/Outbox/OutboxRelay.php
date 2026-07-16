<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use DateInterval;
use Hapa\Core\Clock\Clock;
use Hapa\Core\Configuration\OutboxRelayConfig;
use Hapa\Core\Messaging\MessagePublisher;
use Throwable;

final readonly class OutboxRelay
{
    public function __construct(
        private OutboxRepository $repository,
        private MessagePublisher $publisher,
        private OutboxEnvelopeFactory $envelopes,
        private Clock $clock,
        private OutboxRelayConfig $configuration,
    ) {
    }

    public function runOnce(): OutboxRelayReport
    {
        $now = $this->clock->now();
        $recovered = $this->repository->recoverExpired(
            $now->sub(new DateInterval(sprintf('PT%dS', $this->configuration->lockTimeoutSeconds))),
            $now,
        );
        $messages = $this->repository->claim(
            $this->configuration->workerId,
            $this->configuration->batchSize,
            $now,
        );

        $published = 0;
        $retried = 0;
        $dead = 0;

        foreach ($messages as $message) {
            try {
                $this->publisher->publish(
                    $message->eventType,
                    $this->envelopes->create($message),
                );
                $this->repository->complete($message, $this->clock->now());
                $published++;
            } catch (Throwable $exception) {
                $failedAt = $this->clock->now();
                if ($message->attempts >= $message->maximumAttempts) {
                    $this->repository->dead($message, $failedAt, $exception->getMessage());
                    $dead++;
                    continue;
                }

                $this->repository->retry(
                    $message,
                    $failedAt->add(new DateInterval(sprintf('PT%dS', $this->retryDelay($message)))),
                    $exception->getMessage(),
                );
                $retried++;
            }
        }

        return new OutboxRelayReport(
            $recovered,
            count($messages),
            $published,
            $retried,
            $dead,
        );
    }

    private function retryDelay(ClaimedOutboxMessage $message): int
    {
        $exponent = min(20, max(0, $message->attempts - 1));
        $delay = $this->configuration->retryBaseSeconds * (2 ** $exponent);

        return min($this->configuration->retryMaximumSeconds, $delay);
    }
}
