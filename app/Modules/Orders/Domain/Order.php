<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderAvailabilityChanged;
use Hapa\Modules\Orders\Domain\Event\OrderCreated;
use Hapa\Modules\Orders\Domain\Event\OrderEvent;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;

final class Order
{
    public readonly OrderNumber $number;
    public readonly OrderOrigin $origin;
    public readonly string $externalOrderId;
    public readonly ?int $marketplaceId;
    public readonly ?string $originReference;
    public readonly string $currency;

    private OrderStatus $status;
    private int $version;
    private ?OrderAddress $shippingAddress;
    private ?OrderAddress $billingAddress;
    private DateTimeImmutable $lastOccurredAt;
    private ?OrderStatus $statusBeforeManualReview;

    /** @var array<int, OrderLine> */
    private array $lines = [];

    /** @var list<OrderTransition> */
    private array $transitions = [];

    /** @var list<OrderEvent> */
    private array $events = [];

    /**
     * @param list<OrderLine> $lines
     * @param list<OrderTransition> $transitions
     */
    private function __construct(
        OrderNumber $number,
        OrderOrigin $origin,
        string $externalOrderId,
        ?int $marketplaceId,
        ?string $originReference,
        string $currency,
        OrderStatus $status,
        int $version,
        array $lines,
        ?OrderAddress $shippingAddress,
        ?OrderAddress $billingAddress,
        DateTimeImmutable $lastOccurredAt,
        ?OrderStatus $statusBeforeManualReview,
        array $transitions,
        bool $recordCreation,
    ) {
        $this->number = $number;
        $this->origin = $origin;
        $this->externalOrderId = self::required($externalOrderId, 'ID ordine esterno', 160);
        $this->marketplaceId = $marketplaceId;
        $this->originReference = self::optional($originReference, 'riferimento origine', 160);

        if (!preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw new OrderDomainException('La valuta deve rispettare il formato ISO 4217 di tre lettere maiuscole.');
        }

        $this->currency = $currency;
        self::assertSource($origin, $marketplaceId, $this->originReference);

        if ($version < 1) {
            throw new OrderDomainException('La versione ordine deve essere positiva.');
        }

        foreach ($lines as $line) {
            if (isset($this->lines[$line->lineNumber])) {
                throw new OrderDomainException(sprintf('Il numero riga %d è duplicato.', $line->lineNumber));
            }

            $this->lines[$line->lineNumber] = $line;
        }

        if ($this->lines === []) {
            throw new OrderDomainException('Un ordine deve contenere almeno una riga.');
        }

        ksort($this->lines);

        if ($status === OrderStatus::ManualReview && $statusBeforeManualReview === null) {
            throw new OrderDomainException('Un ordine in revisione manuale deve conservare lo stato precedente.');
        }

        if ($status === OrderStatus::ManualReview && $statusBeforeManualReview !== null) {
            OrderStateMachine::assertCanTransition(OrderStatus::ManualReview, $statusBeforeManualReview);
        }

        if ($status !== OrderStatus::ManualReview && $statusBeforeManualReview !== null) {
            throw new OrderDomainException('Lo stato precedente è ammesso soltanto durante la revisione manuale.');
        }

        $previousTransitionVersion = 1;
        $previousTransitionAt = null;
        $expectedFrom = self::initialStatus($origin);
        $lastTransitionFrom = null;
        foreach ($transitions as $transition) {
            if ($transition->version <= $previousTransitionVersion || $transition->version > $version) {
                throw new OrderDomainException('Lo storico transizioni non è ordinato rispetto alla versione ordine.');
            }

            if ($transition->from !== $expectedFrom) {
                throw new OrderDomainException('Lo storico transizioni non forma una catena di stati coerente.');
            }

            OrderStateMachine::assertCanTransition($transition->from, $transition->to);

            if ($previousTransitionAt !== null && $transition->occurredAt < $previousTransitionAt) {
                throw new OrderDomainException('Lo storico transizioni non è ordinato temporalmente.');
            }

            if ($transition->occurredAt > $lastOccurredAt) {
                throw new OrderDomainException('Lo storico contiene una transizione successiva all’ultimo aggiornamento ordine.');
            }

            $previousTransitionVersion = $transition->version;
            $previousTransitionAt = $transition->occurredAt;
            $lastTransitionFrom = $transition->from;
            $expectedFrom = $transition->to;
        }

        if ($transitions !== [] && $expectedFrom !== $status) {
            throw new OrderDomainException('Lo stato corrente non coincide con l’ultima transizione registrata.');
        }

        if (
            $status === OrderStatus::ManualReview
            && $statusBeforeManualReview !== null
            && $lastTransitionFrom !== null
            && $lastTransitionFrom !== $statusBeforeManualReview
        ) {
            throw new OrderDomainException('Lo stato da ripristinare dopo la revisione manuale non è coerente.');
        }

        $this->status = $status;
        $this->version = $version;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->lastOccurredAt = $lastOccurredAt;
        $this->statusBeforeManualReview = $statusBeforeManualReview;
        $this->transitions = array_values($transitions);

        if ($recordCreation) {
            $this->events[] = new OrderCreated(
                (string) $number,
                $version,
                $lastOccurredAt,
                $origin,
                $status,
            );
        }
    }

    public static function marketplace(
        OrderNumber $number,
        int $marketplaceId,
        string $externalOrderId,
        string $currency,
        DateTimeImmutable $occurredAt,
        OrderLine ...$lines,
    ): self {
        if ($lines === []) {
            throw new OrderDomainException('Un ordine deve contenere almeno una riga.');
        }

        return new self(
            $number,
            OrderOrigin::Marketplace,
            $externalOrderId,
            $marketplaceId,
            null,
            $currency,
            OrderStatus::Imported,
            1,
            array_values($lines),
            null,
            null,
            $occurredAt,
            null,
            [],
            true,
        );
    }

    public static function b2c(
        OrderNumber $number,
        string $storefrontReference,
        string $externalOrderId,
        string $currency,
        DateTimeImmutable $occurredAt,
        OrderLine ...$lines,
    ): self {
        if ($lines === []) {
            throw new OrderDomainException('Un ordine deve contenere almeno una riga.');
        }

        return new self(
            $number,
            OrderOrigin::B2cEcommerce,
            $externalOrderId,
            null,
            $storefrontReference,
            $currency,
            OrderStatus::New,
            1,
            array_values($lines),
            null,
            null,
            $occurredAt,
            null,
            [],
            true,
        );
    }

    /**
     * @param non-empty-list<OrderLine> $lines
     * @param list<OrderTransition> $transitions
     */
    public static function reconstitute(
        OrderNumber $number,
        OrderOrigin $origin,
        string $externalOrderId,
        ?int $marketplaceId,
        ?string $originReference,
        string $currency,
        OrderStatus $status,
        int $version,
        array $lines,
        ?OrderAddress $shippingAddress,
        ?OrderAddress $billingAddress,
        DateTimeImmutable $lastOccurredAt,
        ?OrderStatus $statusBeforeManualReview = null,
        array $transitions = [],
    ): self {
        return new self(
            $number,
            $origin,
            $externalOrderId,
            $marketplaceId,
            $originReference,
            $currency,
            $status,
            $version,
            $lines,
            $shippingAddress,
            $billingAddress,
            $lastOccurredAt,
            $statusBeforeManualReview,
            $transitions,
            false,
        );
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function shippingAddress(): ?OrderAddress
    {
        return $this->shippingAddress;
    }

    public function billingAddress(): ?OrderAddress
    {
        return $this->billingAddress;
    }

    /** @return non-empty-list<OrderLine> */
    public function lines(): array
    {
        $lines = array_values($this->lines);
        if ($lines === []) {
            throw new OrderDomainException('Un ordine deve contenere almeno una riga.');
        }

        return $lines;
    }

    /** @return list<OrderTransition> */
    public function transitions(): array
    {
        return $this->transitions;
    }

    /** @return list<OrderEvent> */
    public function releaseEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

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
        $updates = [];
        foreach ($availability as $lineAvailability) {
            if (isset($updates[$lineAvailability->lineNumber])) {
                throw new OrderDomainException(sprintf(
                    'La disponibilità della riga %d è duplicata.',
                    $lineAvailability->lineNumber,
                ));
            }

            if (!isset($this->lines[$lineAvailability->lineNumber])) {
                throw new OrderDomainException(sprintf(
                    'La riga %d non appartiene all’ordine.',
                    $lineAvailability->lineNumber,
                ));
            }

            $updates[$lineAvailability->lineNumber] = $lineAvailability->quantityAvailable;
        }

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

        if ($allFullyAvailable) {
            foreach ($newLines as $lineNumber => $line) {
                $newLines[$lineNumber] = $line->withFullFulfilment();
            }
            $targetStatus = OrderStatus::GoodsAvailable;
        } elseif ($quantityAvailable > 0) {
            $targetStatus = OrderStatus::PartialAvailable;
        } else {
            $targetStatus = OrderStatus::WaitingGoods;
        }

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

    private static function assertSource(
        OrderOrigin $origin,
        ?int $marketplaceId,
        ?string $originReference,
    ): void {
        if (
            $origin === OrderOrigin::Marketplace
            && ($marketplaceId === null || $marketplaceId < 1 || $originReference !== null)
        ) {
            throw new OrderDomainException('Un ordine marketplace richiede marketplace e vieta il riferimento storefront.');
        }

        if (
            $origin === OrderOrigin::B2cEcommerce
            && ($marketplaceId !== null || $originReference === null)
        ) {
            throw new OrderDomainException('Un ordine B2C richiede il riferimento storefront e vieta il marketplace.');
        }
    }

    private static function initialStatus(OrderOrigin $origin): OrderStatus
    {
        return match ($origin) {
            OrderOrigin::Marketplace => OrderStatus::Imported,
            OrderOrigin::B2cEcommerce => OrderStatus::New,
        };
    }

    private static function required(string $value, string $field, int $maximumLength): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > $maximumLength) {
            throw new OrderDomainException(sprintf(
                'Il campo %s è obbligatorio e non può superare %d caratteri.',
                $field,
                $maximumLength,
            ));
        }

        return $normalized;
    }

    private static function optional(?string $value, string $field, int $maximumLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > $maximumLength) {
            throw new OrderDomainException(sprintf(
                'Il campo %s non può essere vuoto o superare %d caratteri.',
                $field,
                $maximumLength,
            ));
        }

        return $normalized;
    }

    private static function reason(string $reason): string
    {
        $normalized = trim($reason);
        if ($normalized === '' || strlen($normalized) > 255) {
            throw new OrderDomainException('La motivazione è obbligatoria e non può superare 255 caratteri.');
        }

        return $normalized;
    }
}
