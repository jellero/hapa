<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core\Outbox;

use DateTimeImmutable;
use Hapa\Core\Clock\FrozenClock;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Core\Outbox\ProviderCommandPayloadValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderCommandFactoryTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    #[DataProvider('commands')]
    public function testItBuildsEveryVersionTwoProviderCommand(string $eventType, array $payload): void
    {
        $now = new DateTimeImmutable('2026-07-17T20:00:00Z');
        $message = (new ProviderCommandFactory(
            new ProviderCommandPayloadValidator(),
            new FrozenClock($now),
        ))->create($eventType, 'provider_intent', 'aggregate-42', $payload, 'correlation-42');

        self::assertSame($eventType, $message->eventType);
        self::assertSame($eventType, $message->routingKey);
        self::assertSame('hapa.commands', $message->exchangeName);
        self::assertSame(2, $message->schemaVersion);
        self::assertSame($payload['idempotency_key'], $message->idempotencyKey);
        self::assertSame($payload, $message->payload);
        self::assertSame($now, $message->availableAt);
    }

    public function testItRejectsACommandWithoutConfigurationVersion(): void
    {
        $payload = self::common('invalid-command');
        unset($payload['configuration_version']);

        $this->expectException(InvalidArgumentException::class);
        (new ProviderCommandFactory(
            new ProviderCommandPayloadValidator(),
            new FrozenClock(new DateTimeImmutable('2026-07-17T20:00:00Z')),
        ))->create(
            'marketplace.offer.publish.requested',
            'marketplace_offer',
            '42',
            $payload,
            'correlation-42',
        );
    }

    /** @return iterable<string, array{string, array<string, mixed>}> */
    public static function commands(): iterable
    {
        yield 'product' => ['marketplace.product.upsert.requested', [
            ...self::common('product:42:8'),
            'connector' => 'sellrapido',
            'sku' => 'SKU-42',
            'product_version' => 8,
            'fields' => ['title' => 'Prodotto approvato'],
        ]];
        yield 'offer' => ['marketplace.offer.publish.requested', [
            ...self::common('offer:42:7'),
            'connector' => 'sellrapido',
            'offer_id' => '42',
            'downstream_marketplace_code' => 'ibs',
            'catalog_id' => 123456,
            'sku' => 'SKU-42',
            'offer_version' => 7,
            'price_minor' => 1899,
            'currency' => 'EUR',
            'quantity' => 10,
        ]];
        yield 'space purchase' => ['space.purchase_order.submit.requested', [
            ...self::common('purchase:42:2'),
            'purchase_order_id' => '42',
            'purchase_order_version' => 2,
            'lines' => [['sku' => 'SKU-42', 'quantity' => 1]],
        ]];
        yield 'shipment create' => ['shipping.shipment.create.requested', [
            ...self::common('shipment:42:3:create'),
            'shipment_id' => '42',
            'shipment_version' => 3,
            'carrier' => 'gls',
            'recipient' => ['name' => 'Destinatario test'],
            'packages' => [['package_id' => '101', 'weight_grams' => 1200]],
        ]];
        yield 'shipment close' => ['shipping.shipment.close.requested', [
            ...self::common('shipment:42:4:close'),
            'shipment_id' => '42',
            'shipment_version' => 4,
            'provider' => 'gls',
            'tracking_number' => 'TRACK-42',
        ]];
        yield 'label' => ['shipping.label.retrieve.requested', [
            ...self::common('shipment:42:package:101:label:1:pdf'),
            'shipment_id' => '42',
            'package_id' => '101',
            'provider' => 'gls',
            'format' => 'pdf',
        ]];
        yield 'fulfilment' => ['marketplace.fulfilment.publish.requested', [
            ...self::common('fulfilment:42:12'),
            'connector' => 'sellrapido',
            'provider_order_id' => '6714',
            'order_version' => 12,
            'requested_status' => 'sent',
            'tracking_number' => 'TRACK-42',
            'courier_code' => 'GLS',
        ]];
    }

    /** @return array{integration_account_code: string, configuration_version: int, idempotency_key: string} */
    private static function common(string $idempotencyKey): array
    {
        return [
            'integration_account_code' => 'provider-primary',
            'configuration_version' => 4,
            'idempotency_key' => $idempotencyKey,
        ];
    }
}
