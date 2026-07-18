<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules\Marketplace;

use DateTimeImmutable;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Modules\Marketplace\Contract\MarketplaceOrderObservation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MarketplaceOrderObservationTest extends TestCase
{
    public function testItNormalizesTheCanonicalSellRapidoOrder(): void
    {
        $observation = MarketplaceOrderObservation::fromEnvelope($this->message());

        self::assertSame('sellrapido-primary', $observation->integrationAccountCode);
        self::assertSame('ibs', $observation->marketplaceCode);
        self::assertSame('IT', $observation->shippingAddress['country_code']);
        self::assertSame(2200, $observation->rows[0]['tax_rate_basis_points']);
        self::assertSame(900, $observation->totals['order_minor']);
    }

    public function testItRejectsAnUnsupportedProviderStatus(): void
    {
        $message = $this->message();
        $payload = $message->payload;
        $payload['provider_status'] = 'unknown';

        $this->expectException(InvalidArgumentException::class);
        MarketplaceOrderObservation::fromEnvelope(new MessageEnvelope(
            $message->messageId,
            $message->eventType,
            $message->schemaVersion,
            $message->occurredAt,
            $message->correlationId,
            $message->causationId,
            $payload,
        ));
    }

    private function message(): MessageEnvelope
    {
        return new MessageEnvelope(
            'message-sellrapido-1',
            'marketplace.order.observed',
            1,
            new DateTimeImmutable('2026-07-18T08:00:05+00:00'),
            'correlation-sellrapido-1',
            null,
            [
                'integration_account_code' => 'sellrapido-primary',
                'connector' => 'sellrapido',
                'provider_order_id' => '6714',
                'external_order_id' => 'IBS-13067',
                'marketplace_code' => 'IBS',
                'channel_code' => 'Italy',
                'provider_status' => 'accepted',
                'source_version' => '2026-07-18T08:00:00Z',
                'ordered_at' => '2026-07-18T07:00:00Z',
                'modified_at' => '2026-07-18T08:00:00Z',
                'currency' => 'EUR',
                'totals' => [
                    'order_minor' => 900,
                    'shipping_minor' => 500,
                    'marketplace_fee_minor' => 245,
                    'tax_minor' => 0,
                ],
                'customer' => [
                    'external_customer_id' => 'buyer-42',
                    'name' => 'Mario Rossi',
                    'email' => 'mario@example.test',
                    'phone' => '+390000000000',
                    'fiscal_code' => null,
                    'vat_number' => null,
                ],
                'shipping_address' => [
                    'name' => 'Mario Rossi',
                    'address1' => 'Via Roma 1',
                    'address2' => '',
                    'postal_code' => '20100',
                    'city' => 'Milano',
                    'province' => 'MI',
                    'country' => 'ITA',
                ],
                'rows' => [[
                    'provider_row_id' => '7040',
                    'transaction_id' => '10072442519011',
                    'external_product_id' => '197139218223',
                    'sku' => 'SKU-00123',
                    'ean' => '1234567890123',
                    'title' => 'Titolo venduto',
                    'quantity' => 1,
                    'unit_price_minor' => 400,
                    'total_price_minor' => 400,
                    'shipping_minor' => 500,
                    'vat_percent' => '22.00',
                ]],
                'observed_at' => '2026-07-18T08:00:05Z',
            ],
        );
    }
}
