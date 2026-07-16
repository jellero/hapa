<?php

declare(strict_types=1);

namespace Hapa\Core\Outbox;

use InvalidArgumentException;

final readonly class RetryBackoff
{
    public function __construct(
        private int $baseSeconds,
        private int $maximumSeconds,
    ) {
        if ($baseSeconds < 1 || $maximumSeconds < $baseSeconds) {
            throw new InvalidArgumentException('Configurazione backoff non valida.');
        }
    }

    public function delaySeconds(int $attempt, ?int $retryAfterSeconds = null): int
    {
        if ($attempt < 1) {
            throw new InvalidArgumentException('Il tentativo retry deve essere positivo.');
        }

        if ($retryAfterSeconds !== null) {
            return min($this->maximumSeconds, max(1, $retryAfterSeconds));
        }

        $delay = $this->baseSeconds;
        for ($currentAttempt = 1; $currentAttempt < $attempt && $delay < $this->maximumSeconds; $currentAttempt++) {
            $delay = $delay > intdiv($this->maximumSeconds, 2)
                ? $this->maximumSeconds
                : min($this->maximumSeconds, $delay * 2);
        }
        $jitterMaximum = max(1, min($this->baseSeconds, intdiv($delay, 4)));

        return min($this->maximumSeconds, $delay + random_int(0, $jitterMaximum));
    }
}
