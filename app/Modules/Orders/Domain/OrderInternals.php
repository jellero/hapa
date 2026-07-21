<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderAvailabilityChanged;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;

trait OrderInternals
{
    private function changeStatus(
        OrderStatus $targetStatus,
        DateTimeImmutable $occurredAt,
        ?string $reason = null,
    ): void {
        $this->assertTimestamp($occurredAt);
        OrderStateMachine::assertCanTransition($this->status, $targetStatus);

        $fromStatus = $this->status;
        $nextVersion = $this->version + 1;
        $transition = new OrderTransition(
            $fromStatus,
            $targetStatus,
            $nextVersion,
            $occurredAt,
            $reason,
        );

        $this->status = $targetStatus;
        $this->version = $nextVersion;
        $this->lastOccurredAt = $occurredAt;
        $this->transitions[] = $transition;
        $this->events[] = new OrderStatusChanged(
            (string) $this->number,
            $nextVersion,
            $occurredAt,
            $fromStatus,
            $targetStatus,
            $transition->reason,
        );
    }

    private function advanceVersion(DateTimeImmutable $occurredAt): void
    {
        $this->version++;
        $this->lastOccurredAt = $occurredAt;
    }

    private function assertTimestamp(DateTimeImmutable $occurredAt): void
    {
        if ($occurredAt < $this->lastOccurredAt) {
            throw new OrderDomainException('Un evento ordine non può precedere l’ultimo aggiornamento registrato.');
        }
    }

    private function assertAddressCanChange(): void
    {
        if (!in_array($this->status, [
            OrderStatus::New,
            OrderStatus::Imported,
            OrderStatus::Accepted,
            OrderStatus::WaitingAddress,
        ], true)) {
            throw new OrderDomainException('Gli indirizzi ordine non sono modificabili nello stato corrente.');
        }
    }

    private function assertFulfilmentPlan(): void
    {
        $quantityToShip = 0;
        foreach ($this->lines as $line) {
            if (!$line->isFulfilmentPlanned()) {
                throw new OrderDomainException(sprintf(
                    'La riga %d non contiene una decisione di fulfilment completa.',
                    $line->lineNumber,
                ));
            }

            $quantityToShip += $line->quantityToShip;
        }

        if ($quantityToShip < 1) {
            throw new OrderDomainException('Il picking richiede almeno una unità da spedire.');
        }
    }

    private function hasCancelledQuantities(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->isPartial()) {
                return true;
            }
        }

        return false;
    }

    private static function assertCurrency(string $currency): void
    {
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new OrderDomainException('La valuta deve rispettare il formato ISO 4217 di tre lettere maiuscole.');
        }
    }

    private static function assertVersion(int $version): void
    {
        if ($version < 1) {
            throw new OrderDomainException('La versione ordine deve essere positiva.');
        }
    }

    /**
     * @param list<OrderLine> $lines
     * @return array<int,OrderLine>
     */
    private static function indexLines(array $lines): array
    {
        $indexed = [];
        foreach ($lines as $line) {
            if (isset($indexed[$line->lineNumber])) {
                throw new OrderDomainException(sprintf('Il numero riga %d è duplicato.', $line->lineNumber));
            }
            $indexed[$line->lineNumber] = $line;
        }
        if ($indexed === []) {
            throw new OrderDomainException(self::LINE_REQUIRED);
        }
        ksort($indexed);
        return $indexed;
    }

    private static function assertManualReviewState(OrderStatus $status, ?OrderStatus $previous): void
    {
        if ($status === OrderStatus::ManualReview && $previous === null) {
            throw new OrderDomainException('Un ordine in revisione manuale deve conservare lo stato precedente.');
        }
        if ($status === OrderStatus::ManualReview && $previous !== null) {
            OrderStateMachine::assertCanTransition(OrderStatus::ManualReview, $previous);
        }
        if ($status !== OrderStatus::ManualReview && $previous !== null) {
            throw new OrderDomainException('Lo stato precedente è ammesso soltanto durante la revisione manuale.');
        }
    }

    /** @param list<OrderTransition> $transitions */
    private static function assertTransitionHistory(OrderOrigin $origin, OrderStatus $status, ?OrderStatus $previous, int $version, DateTimeImmutable $lastOccurredAt, array $transitions): void
    {
        $previousVersion = 1;
        $previousAt = null;
        $expectedFrom = self::initialStatus($origin);
        $lastFrom = null;
        foreach ($transitions as $transition) {
            self::assertTransitionSequence($transition, $previousVersion, $previousAt, $expectedFrom, $version, $lastOccurredAt);
            $previousVersion = $transition->version;
            $previousAt = $transition->occurredAt;
            $lastFrom = $transition->from;
            $expectedFrom = $transition->to;
        }
        if ($transitions !== [] && $expectedFrom !== $status) {
            throw new OrderDomainException('Lo stato corrente non coincide con l’ultima transizione registrata.');
        }
        if ($status === OrderStatus::ManualReview && $previous !== null && $lastFrom !== null && $lastFrom !== $previous) {
            throw new OrderDomainException('Lo stato da ripristinare dopo la revisione manuale non è coerente.');
        }
    }

    private static function assertTransitionSequence(OrderTransition $transition, int $previousVersion, ?DateTimeImmutable $previousAt, OrderStatus $expectedFrom, int $version, DateTimeImmutable $lastOccurredAt): void
    {
        if ($transition->version <= $previousVersion || $transition->version > $version) {
            throw new OrderDomainException('Lo storico transizioni non è ordinato rispetto alla versione ordine.');
        }
        if ($transition->from !== $expectedFrom) {
            throw new OrderDomainException('Lo storico transizioni non forma una catena di stati coerente.');
        }
        OrderStateMachine::assertCanTransition($transition->from, $transition->to);
        if ($previousAt !== null && $transition->occurredAt < $previousAt) {
            throw new OrderDomainException('Lo storico transizioni non è ordinato temporalmente.');
        }
        if ($transition->occurredAt > $lastOccurredAt) {
            throw new OrderDomainException('Lo storico contiene una transizione successiva all’ultimo aggiornamento ordine.');
        }
    }

    /**
     * @param list<OrderLineAvailability> $availability
     * @return array<int,int>
     */
    private function availabilityUpdates(array $availability): array
    {
        $updates = [];
        foreach ($availability as $item) {
            if (isset($updates[$item->lineNumber])) {
                throw new OrderDomainException(sprintf('La disponibilità della riga %d è duplicata.', $item->lineNumber));
            }
            if (!isset($this->lines[$item->lineNumber])) {
                throw new OrderDomainException(sprintf('La riga %d non appartiene all’ordine.', $item->lineNumber));
            }
            $updates[$item->lineNumber] = $item->quantityAvailable;
        }
        return $updates;
    }

    /**
     * @param array<int,OrderLine> $lines
     * @return array{array<int,OrderLine>,OrderStatus}
     */
    private static function availabilityTarget(array $lines, bool $fullyAvailable, int $quantityAvailable): array
    {
        if ($fullyAvailable) {
            return [array_map(static fn (OrderLine $line): OrderLine => $line->withFullFulfilment(), $lines), OrderStatus::GoodsAvailable];
        }
        return [$lines, $quantityAvailable > 0 ? OrderStatus::PartialAvailable : OrderStatus::WaitingGoods];
    }

}
