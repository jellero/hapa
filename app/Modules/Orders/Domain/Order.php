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
    use OrderAccessors;
    use OrderWorkflow;
    use OrderInternals;
    use OrderValidation;

    private const LINE_REQUIRED = 'Un ordine deve contenere almeno una riga.';
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

    /** @param array{number:OrderNumber,origin:OrderOrigin,external_order_id:string,marketplace_id:?int,origin_reference:?string,currency:string,status:OrderStatus,version:int,lines:list<OrderLine>,shipping_address:?OrderAddress,billing_address:?OrderAddress,last_occurred_at:DateTimeImmutable,status_before_manual_review:?OrderStatus,transitions:list<OrderTransition>} $state */
    private function __construct(array $state, bool $recordCreation)
    {
        ['number' => $number, 'origin' => $origin, 'external_order_id' => $externalOrderId,
            'marketplace_id' => $marketplaceId, 'origin_reference' => $originReference, 'currency' => $currency,
            'status' => $status, 'version' => $version, 'lines' => $lines, 'shipping_address' => $shippingAddress,
            'billing_address' => $billingAddress, 'last_occurred_at' => $lastOccurredAt,
            'status_before_manual_review' => $statusBeforeManualReview, 'transitions' => $transitions] = $state;
        $this->number = $number;
        $this->origin = $origin;
        $this->externalOrderId = self::required($externalOrderId, 'ID ordine esterno', 160);
        $this->marketplaceId = $marketplaceId;
        $this->originReference = self::optional($originReference, 'riferimento origine', 160);

        self::assertCurrency($currency);
        $this->currency = $currency;
        self::assertSource($origin, $marketplaceId, $this->originReference);
        self::assertVersion($version);
        $this->lines = self::indexLines($lines);
        self::assertManualReviewState($status, $statusBeforeManualReview);
        self::assertTransitionHistory($origin, $status, $statusBeforeManualReview, $version, $lastOccurredAt, $transitions);

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
            throw new OrderDomainException(self::LINE_REQUIRED);
        }

        return new self(self::initialState([
            'number' => $number, 'origin' => OrderOrigin::Marketplace, 'external_order_id' => $externalOrderId,
            'marketplace_id' => $marketplaceId, 'origin_reference' => null, 'currency' => $currency, 'status' => OrderStatus::Imported,
        ], $occurredAt, array_values($lines)), true);
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
            throw new OrderDomainException(self::LINE_REQUIRED);
        }

        return new self(self::initialState([
            'number' => $number, 'origin' => OrderOrigin::B2cEcommerce, 'external_order_id' => $externalOrderId,
            'marketplace_id' => null, 'origin_reference' => $storefrontReference, 'currency' => $currency, 'status' => OrderStatus::New,
        ], $occurredAt, array_values($lines)), true);
    }

    /** @param array{number:OrderNumber,origin:OrderOrigin,external_order_id:string,marketplace_id:?int,origin_reference:?string,currency:string,status:OrderStatus,version:int,lines:list<OrderLine>,shipping_address:?OrderAddress,billing_address:?OrderAddress,last_occurred_at:DateTimeImmutable,status_before_manual_review:?OrderStatus,transitions:list<OrderTransition>} $state */
    public static function reconstitute(array $state): self
    {
        return new self($state, false);
    }

    /**
     * @param array{number:OrderNumber,origin:OrderOrigin,external_order_id:string,marketplace_id:?int,origin_reference:?string,currency:string,status:OrderStatus} $identity
     * @param list<OrderLine> $lines
     * @return array{number:OrderNumber,origin:OrderOrigin,external_order_id:string,marketplace_id:?int,origin_reference:?string,currency:string,status:OrderStatus,version:int,lines:list<OrderLine>,shipping_address:null,billing_address:null,last_occurred_at:DateTimeImmutable,status_before_manual_review:null,transitions:list<OrderTransition>}
     */
    private static function initialState(array $identity, DateTimeImmutable $occurredAt, array $lines): array
    {
        return [...$identity, 'version' => 1,
            'lines' => array_values($lines), 'shipping_address' => null, 'billing_address' => null,
            'last_occurred_at' => $occurredAt, 'status_before_manual_review' => null, 'transitions' => []];
    }


}
