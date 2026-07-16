<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core\Outbox;

use DateTimeImmutable;
use Hapa\Core\Outbox\ClaimedOutboxMessage;
use Hapa\Core\Outbox\OutboxEnvelopeFactory;
use PHPUnit\Framework\TestCase;

final class OutboxEnvelopeFactoryTest extends TestCase
{
    public function testItBuildsTheCanonicalEnvelopeWithAStableUuid(): void
    {
        $message = new ClaimedOutboxMessage(
            42,
            'order',
            'ORD-001',
            'order.changed',
            [
                'order_number' => 'ORD-001',
                'version' => 2,
                'change_type' => 'order.status_changed',
                'status' => 'accepted',
                'occurred_at' => '2026-07-16T10:00:00+00:00',
            ],
            'order:ORD-001:v2:order.status_changed',
            'order-ORD-001-v2',
            1,
            1,
            10,
            'relay-1',
            'lock-token',
            new DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            new DateTimeImmutable('2026-07-16T10:00:01+00:00'),
        );

        $envelope = (new OutboxEnvelopeFactory())->create($message);
        $decoded = json_decode($envelope->toJson(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('1ebac0f3-24f7-55c5-b6de-d82196c78b8a', $envelope->messageId);
        self::assertSame('order.changed', $envelope->eventType);
        self::assertSame('2026-07-16T10:00:00+00:00', $decoded['occurred_at']);
        self::assertSame('order-ORD-001-v2', $decoded['correlation_id']);
        self::assertNull($decoded['causation_id']);
        self::assertSame($message->payload, $decoded['payload']);
    }

    public function testItFallsBackToThePersistedCreationTime(): void
    {
        $message = new ClaimedOutboxMessage(
            43,
            'catalog',
            'SKU-001',
            'catalog.product.changed',
            ['sku' => 'SKU-001'],
            'catalog:SKU-001:v1',
            'catalog-SKU-001-v1',
            1,
            1,
            10,
            'relay-1',
            'lock-token',
            new DateTimeImmutable('2026-07-16T10:00:00+00:00'),
            new DateTimeImmutable('2026-07-16T09:59:58+00:00'),
        );

        $envelope = (new OutboxEnvelopeFactory())->create($message);

        self::assertSame('2026-07-16T09:59:58+00:00', $envelope->occurredAt->format(DATE_ATOM));
    }
}
