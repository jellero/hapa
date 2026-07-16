<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

use DateTimeImmutable;

final readonly class OrderTransition
{
    public ?string $reason;

    public function __construct(
        public OrderStatus $from,
        public OrderStatus $to,
        public int $version,
        public DateTimeImmutable $occurredAt,
        ?string $reason = null,
    ) {
        if ($from === $to) {
            throw new OrderDomainException('Uno storico transizione richiede due stati distinti.');
        }

        if ($version < 2) {
            throw new OrderDomainException('La versione di una transizione deve essere almeno 2.');
        }

        $this->reason = self::normalizeReason($reason);
    }

    private static function normalizeReason(?string $reason): ?string
    {
        if ($reason === null) {
            return null;
        }

        $normalized = trim($reason);
        if ($normalized === '' || strlen($normalized) > 255) {
            throw new OrderDomainException('La motivazione non può essere vuota o superare 255 caratteri.');
        }

        return $normalized;
    }
}
