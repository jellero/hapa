<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules;

use Hapa\Modules\Brt\Contract\BrtAdapter;
use Hapa\Modules\Gls\Contract\GlsAdapter;
use Hapa\Modules\Shipping\Contract\CarrierAdapter;
use Hapa\Modules\Shipping\Contract\CarrierCode;
use Hapa\Modules\Shipping\Contract\ShipmentRequest;
use Hapa\Modules\Shipping\Contract\ShipmentResult;
use Hapa\Modules\Space\Contract\AvailabilityLine;
use Hapa\Modules\Space\Contract\SpaceOrderRequest;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class IntegrationContractTest extends TestCase
{
    public function testItAcceptsValidSpaceAndShippingContracts(): void
    {
        $spaceOrder = new SpaceOrderRequest('order-1', [['sku' => 'SKU-1', 'quantity' => 2]]);
        $availability = new AvailabilityLine('SKU-1', 2, 1, 1);
        $shipment = $this->shipment();
        $result = new ShipmentResult('shipment-1', 'tracking-1', 'label-1');

        self::assertSame('order-1', $spaceOrder->orderReference);
        self::assertSame(1, $availability->available);
        self::assertSame('1.250', $shipment->weightKg);
        self::assertSame('tracking-1', $result->trackingNumber);
    }

    public function testItRegistersGlsAndBrtBehindTheSharedCarrierContract(): void
    {
        self::assertSame(['GLS', 'BRT'], array_column(CarrierCode::cases(), 'value'));
        self::assertContains(
            CarrierAdapter::class,
            (new ReflectionClass(GlsAdapter::class))->getInterfaceNames(),
        );
        self::assertContains(
            CarrierAdapter::class,
            (new ReflectionClass(BrtAdapter::class))->getInterfaceNames(),
        );
    }

    /** @param non-empty-list<array{sku: string, quantity: int}> $lines */
    #[DataProvider('invalidSpaceOrders')]
    public function testItRejectsInvalidSpaceOrders(string $reference, array $lines): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpaceOrderRequest($reference, $lines);
    }

    /** @return iterable<string, array{string, array<array{sku: string, quantity: int}>}> */
    public static function invalidSpaceOrders(): iterable
    {
        yield 'riferimento vuoto' => ['', [['sku' => 'SKU-1', 'quantity' => 1]]];
        yield 'righe vuote' => ['order-1', []];
        yield 'sku vuoto' => ['order-1', [['sku' => '', 'quantity' => 1]]];
        yield 'quantità nulla' => ['order-1', [['sku' => 'SKU-1', 'quantity' => 0]]];
    }

    public function testItRejectsNegativeAvailability(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AvailabilityLine('SKU-1', 1, -1, 2);
    }

    #[DataProvider('invalidShipmentValues')]
    public function testItRejectsInvalidShipments(int $packages, string $weight, string $countryCode): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->shipment($packages, $weight, $countryCode);
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function invalidShipmentValues(): iterable
    {
        yield 'nessun collo' => [0, '1.000', 'IT'];
        yield 'peso nullo' => [1, '0', 'IT'];
        yield 'precisione eccessiva' => [1, '1.0001', 'IT'];
        yield 'paese minuscolo' => [1, '1.000', 'it'];
    }

    public function testItRejectsAnEmptyShipmentResult(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShipmentResult('', 'tracking-1', 'label-1');
    }

    private function shipment(
        int $packages = 1,
        string $weight = '1.250',
        string $countryCode = 'IT',
    ): ShipmentRequest {
        return new ShipmentRequest(
            'order-1',
            $packages,
            $weight,
            'Mario Rossi',
            'Via Roma 1',
            null,
            '00100',
            'Roma',
            'RM',
            $countryCode,
        );
    }
}
