<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderAvailabilityChanged;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;

trait OrderWorkflow
{
    public function assertExpectedVersion(int $expectedVersion): void
    {
        if ($expectedVersion !== $this->version) {
            throw new StaleOrderVersion($expectedVersion, $this->version);
        }
    }

    public function accept(DateTimeImmutable $occurredAt): void
    {
        if ($this->status === OrderStatus::WaitingAddress && $this->shippingAddress === null) {
            throw new OrderDomainException('L’ordine resta in attesa finché non viene acquisito un indirizzo di spedizione.');
        }

        $this->changeStatus(OrderStatus::Accepted, $occurredAt);
    }

    public function waitForAddress(DateTimeImmutable $occurredAt): void
    {
        if ($this->shippingAddress !== null) {
            throw new OrderDomainException('Un ordine con indirizzo di spedizione non può entrare in attesa indirizzo.');
        }

        $this->changeStatus(OrderStatus::WaitingAddress, $occurredAt);
    }

    public function attachShippingAddress(OrderAddress $address, DateTimeImmutable $occurredAt): void
    {
        $this->assertAddressCanChange();
        $this->assertTimestamp($occurredAt);
        $this->shippingAddress = $address;

        if ($this->status === OrderStatus::WaitingAddress) {
            $this->changeStatus(OrderStatus::Accepted, $occurredAt);
        } else {
            $this->advanceVersion($occurredAt);
        }

        $this->events[] = new OrderAddressChanged(
            (string) $this->number,
            $this->version,
            $occurredAt,
            OrderAddressType::Shipping,
        );
    }

    public function attachBillingAddress(OrderAddress $address, DateTimeImmutable $occurredAt): void
    {
        $this->assertAddressCanChange();
        $this->assertTimestamp($occurredAt);
        $this->billingAddress = $address;
        $this->advanceVersion($occurredAt);
        $this->events[] = new OrderAddressChanged(
            (string) $this->number,
            $this->version,
            $occurredAt,
            OrderAddressType::Billing,
        );
    }

    public function submitToSpace(DateTimeImmutable $occurredAt): void
    {
        if ($this->shippingAddress === null) {
            throw new OrderDomainException('L’indirizzo di spedizione è obbligatorio prima dell’invio a Space.');
        }

        $this->changeStatus(OrderStatus::SentToSpace, $occurredAt);
    }

    public function waitForGoods(DateTimeImmutable $occurredAt): void
    {
        $this->changeStatus(OrderStatus::WaitingGoods, $occurredAt);
    }

    public function recordAvailability(
        DateTimeImmutable $occurredAt,
        OrderLineAvailability ...$availability,
    ): void {
        if (!in_array($this->status, [
            OrderStatus::WaitingGoods,
            OrderStatus::GoodsAvailable,
            OrderStatus::PartialAvailable,
        ], true)) {
            throw new InvalidOrderTransition($this->status, OrderStatus::PartialAvailable);
        }

        $this->assertTimestamp($occurredAt);
        $updates = $this->availabilityUpdates(array_values($availability));

        if (count($updates) !== count($this->lines)) {
            throw new OrderDomainException('La disponibilità deve includere tutte le righe dell’ordine.');
        }

        $newLines = [];
        $quantityOrdered = 0;
        $quantityAvailable = 0;
        $allFullyAvailable = true;

        foreach ($this->lines as $lineNumber => $line) {
            $updatedLine = $line->withAvailability($updates[$lineNumber]);
            $newLines[$lineNumber] = $updatedLine;
            $quantityOrdered += $updatedLine->quantityOrdered;
            $quantityAvailable += $updatedLine->quantityAvailable;
            $allFullyAvailable = $allFullyAvailable && $updatedLine->isFullyAvailable();
        }

        [$newLines, $targetStatus] = self::availabilityTarget($newLines, $allFullyAvailable, $quantityAvailable);

        if ($targetStatus !== $this->status) {
            OrderStateMachine::assertCanTransition($this->status, $targetStatus);
        }

        $this->lines = $newLines;

        if ($targetStatus === $this->status) {
            $this->advanceVersion($occurredAt);
        } else {
            $this->changeStatus($targetStatus, $occurredAt);
        }

        $this->events[] = new OrderAvailabilityChanged(
            (string) $this->number,
            $this->version,
            $occurredAt,
            $quantityOrdered,
            $quantityAvailable,
            $targetStatus,
        );
    }

    public function confirmPartial(
        DateTimeImmutable $occurredAt,
        OrderLineDecision ...$decisions,
    ): void {
        if ($this->status !== OrderStatus::PartialAvailable) {
            throw new InvalidOrderTransition($this->status, OrderStatus::PartialConfirmed);
        }

        $this->assertTimestamp($occurredAt);
        $decisionsByLine = [];
        foreach ($decisions as $decision) {
            if (isset($decisionsByLine[$decision->lineNumber])) {
                throw new OrderDomainException(sprintf(
                    'La decisione della riga %d è duplicata.',
                    $decision->lineNumber,
                ));
            }

            if (!isset($this->lines[$decision->lineNumber])) {
                throw new OrderDomainException(sprintf(
                    'La riga %d non appartiene all’ordine.',
                    $decision->lineNumber,
                ));
            }

            $decisionsByLine[$decision->lineNumber] = $decision;
        }

        if (count($decisionsByLine) !== count($this->lines)) {
            throw new OrderDomainException('La decisione parziale deve includere tutte le righe dell’ordine.');
        }

        $newLines = [];
        $quantityToShip = 0;
        $quantityToCancel = 0;
        foreach ($this->lines as $lineNumber => $line) {
            $decision = $decisionsByLine[$lineNumber];
            $updatedLine = $line->withDecision(
                $decision->quantityToShip,
                $decision->quantityToCancel,
            );
            $newLines[$lineNumber] = $updatedLine;
            $quantityToShip += $updatedLine->quantityToShip;
            $quantityToCancel += $updatedLine->quantityToCancel;
        }

        if ($quantityToShip < 1 || $quantityToCancel < 1) {
            throw new OrderDomainException('Un parziale richiede quantità sia da spedire sia da annullare.');
        }

        OrderStateMachine::assertCanTransition($this->status, OrderStatus::PartialConfirmed);
        $this->lines = $newLines;
        $this->changeStatus(OrderStatus::PartialConfirmed, $occurredAt);
    }

    public function startPicking(DateTimeImmutable $occurredAt): void
    {
        $this->assertFulfilmentPlan();
        $this->changeStatus(OrderStatus::Picking, $occurredAt);
    }

    public function completePicking(DateTimeImmutable $occurredAt): void
    {
        $this->assertFulfilmentPlan();
        $this->changeStatus(OrderStatus::ReadyForCarrier, $occurredAt);
    }

    public function registerLabel(DateTimeImmutable $occurredAt): void
    {
        $this->changeStatus(OrderStatus::LabelAvailable, $occurredAt);
    }

    public function markTrackingSent(DateTimeImmutable $occurredAt): void
    {
        $this->changeStatus(OrderStatus::TrackingSent, $occurredAt);
    }

    public function completeFulfilment(DateTimeImmutable $occurredAt): void
    {
        $targetStatus = $this->hasCancelledQuantities()
            ? OrderStatus::CompletedPartial
            : OrderStatus::FulfilmentCompleted;
        $this->changeStatus($targetStatus, $occurredAt);
    }

    public function cancel(string $reason, DateTimeImmutable $occurredAt): void
    {
        $this->changeStatus(OrderStatus::Cancelled, $occurredAt, self::reason($reason));
    }

    public function placeInManualReview(string $reason, DateTimeImmutable $occurredAt): void
    {
        if ($this->status === OrderStatus::ManualReview || OrderStateMachine::isTerminal($this->status)) {
            throw new InvalidOrderTransition($this->status, OrderStatus::ManualReview);
        }

        $previousStatus = $this->status;
        $this->changeStatus(OrderStatus::ManualReview, $occurredAt, self::reason($reason));
        $this->statusBeforeManualReview = $previousStatus;
    }

    public function resolveManualReview(string $reason, DateTimeImmutable $occurredAt): void
    {
        if ($this->status !== OrderStatus::ManualReview || $this->statusBeforeManualReview === null) {
            throw new OrderDomainException('L’ordine non contiene una revisione manuale risolvibile.');
        }

        $targetStatus = $this->statusBeforeManualReview;
        $this->changeStatus($targetStatus, $occurredAt, self::reason($reason));
        $this->statusBeforeManualReview = null;
    }

}
