<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core\Messaging;

use DateTimeImmutable;
use Hapa\Core\Messaging\MessageEnvelope;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MessageEnvelopeTest extends TestCase
{
    public function testItRoundTripsAValidInboundEnvelope(): void
    {
        $message = new MessageEnvelope(
            '018f4bd8-d743-7f52-a8a1-10d6477c4120',
            'integration.transport.probe',
            1,
            new DateTimeImmutable('2026-07-16T20:00:00+00:00'),
            '018f4bd8-d743-7f52-a8a1-10d6477c4121',
            null,
            ['probe_id' => 'probe-1'],
        );

        $decoded = MessageEnvelope::fromJson($message->toJson());

        self::assertSame($message->messageId, $decoded->messageId);
        self::assertSame($message->eventType, $decoded->eventType);
        self::assertSame($message->schemaVersion, $decoded->schemaVersion);
        self::assertSame($message->occurredAt->format(DATE_ATOM), $decoded->occurredAt->format(DATE_ATOM));
        self::assertSame($message->correlationId, $decoded->correlationId);
        self::assertNull($decoded->causationId);
        self::assertSame($message->payload, $decoded->payload);
    }

    public function testItRejectsANonObjectPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MessageEnvelope::fromJson(json_encode([
            'message_id' => 'message-1',
            'event_type' => 'integration.transport.probe',
            'schema_version' => 1,
            'occurred_at' => '2026-07-16T20:00:00+00:00',
            'correlation_id' => 'correlation-1',
            'causation_id' => null,
            'payload' => 'not-an-object',
        ], JSON_THROW_ON_ERROR));
    }

    public function testItRejectsAnInvalidNullableCausationId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MessageEnvelope::fromJson(json_encode([
            'message_id' => 'message-1',
            'event_type' => 'integration.transport.probe',
            'schema_version' => 1,
            'occurred_at' => '2026-07-16T20:00:00+00:00',
            'correlation_id' => 'correlation-1',
            'causation_id' => 42,
            'payload' => ['probe_id' => 'probe-1'],
        ], JSON_THROW_ON_ERROR));
    }

    public function testItRejectsAnInvalidOccurredAtAsAnInvalidEnvelope(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('occurred_at deve essere una data valida.');

        MessageEnvelope::fromJson(json_encode([
            'message_id' => 'message-1',
            'event_type' => 'integration.transport.probe',
            'schema_version' => 1,
            'occurred_at' => 'not-a-date',
            'correlation_id' => 'correlation-1',
            'causation_id' => null,
            'payload' => ['probe_id' => 'probe-1'],
        ], JSON_THROW_ON_ERROR));
    }
}
