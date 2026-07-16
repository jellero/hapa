<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use Hapa\Core\Clock\Clock;
use Throwable;

final readonly class OutboxWorker
{
    public function __construct(
        private OutboxRepository $repository,
        private OutboxHandlerRegistry $handlers,
        private RetryBackoff $backoff,
        private Clock $clock,
        private int $lockTimeoutSeconds,
    ) {
    }

    public function runOnce(string $workerId, int $limit): OutboxWorkerReport
    {
        $now = $this->clock->now();
        $recovered = $this->repository->recoverExpired(
            $now->modify(sprintf('-%d seconds', $this->lockTimeoutSeconds)),
            $now,
        );
        $messages = $this->repository->claim($workerId, $limit, $now);
        $completed = 0;
        $retried = 0;
        $dead = 0;

        foreach ($messages as $message) {
            try {
                $this->handlers->handlerFor($message->eventType)->handle($message);
            } catch (PermanentProcessingFailure | UnsupportedOutboxEvent $exception) {
                $this->repository->dead($message, $this->clock->now(), $exception->getMessage());
                $dead++;
                continue;
            } catch (Throwable $exception) {
                if ($message->attempts >= $message->maximumAttempts) {
                    $this->repository->dead($message, $this->clock->now(), $exception->getMessage());
                    $dead++;
                    continue;
                }

                $retryAfter = $exception instanceof TemporaryProcessingFailure
                    ? $exception->retryAfterSeconds
                    : null;
                $availableAt = $this->clock->now()->modify(sprintf(
                    '+%d seconds',
                    $this->backoff->delaySeconds($message->attempts, $retryAfter),
                ));
                $this->repository->retry($message, $availableAt, $exception->getMessage());
                $retried++;
                continue;
            }

            $this->repository->complete($message, $this->clock->now());
            $completed++;
        }

        return new OutboxWorkerReport($recovered, count($messages), $completed, $retried, $dead);
    }
}
