<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core\Messaging;

use DateTimeImmutable;
use Hapa\Core\Messaging\InboundMessageHandler;
use Hapa\Core\Messaging\InboundMessageHandlerRegistry;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Core\Messaging\TransportProbeHandler;
use Hapa\Core\Messaging\UnsupportedInboundMessage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InboundMessageHandlerRegistryTest extends TestCase
{
    public function testItHandlesOnlyExplicitlyRegisteredEvents(): void
    {
        $registry = new InboundMessageHandlerRegistry([new TransportProbeHandler()]);
        $message = $this->message('integration.transport.probe', ['probe_id' => 'probe-1']);

        self::assertTrue($registry->supports($message->eventType));
        $registry->handle($message);
        self::assertFalse($registry->supports('provider.unknown'));
    }

    public function testItRejectsUnsupportedEvents(): void
    {
        $registry = new InboundMessageHandlerRegistry([new TransportProbeHandler()]);

        $this->expectException(UnsupportedInboundMessage::class);
        $registry->handle($this->message('provider.unknown', []));
    }

    public function testItRejectsDuplicateHandlerDeclarations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InboundMessageHandlerRegistry([
            new DuplicateProbeHandler(),
            new DuplicateProbeHandler(),
        ]);
    }

    public function testTheTransportProbeValidatesItsMinimalPayload(): void
    {
        $registry = new InboundMessageHandlerRegistry([new TransportProbeHandler()]);

        $this->expectException(InvalidArgumentException::class);
        $registry->handle($this->message('integration.transport.probe', []));
    }

    /** @param array<string, mixed> $payload */
    private function message(string $eventType, array $payload): MessageEnvelope
    {
        return new MessageEnvelope(
            'message-' . str_replace('.', '-', $eventType),
            $eventType,
            1,
            new DateTimeImmutable('2026-07-16T20:00:00+00:00'),
            'correlation-1',
            null,
            $payload,
        );
    }
}

final class DuplicateProbeHandler implements InboundMessageHandler
{
    public function eventTypes(): array
    {
        return ['integration.transport.probe'];
    }

    public function handle(MessageEnvelope $message): void
    {
    }
}
