<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules;

use DateTimeImmutable;
use Hapa\Core\Messaging\MessageEnvelope;
use Hapa\Modules\Space\Contract\SpaceCatalogObservation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SpaceCatalogObservationTest extends TestCase
{
    public function testItExplainsANameThatExceedsTheByteLimit(): void
    {
        $message = new MessageEnvelope(
            'message-1',
            'space.catalog.item.observed',
            1,
            new DateTimeImmutable('2026-07-31T08:00:00+00:00'),
            'correlation-1',
            null,
            [
                'supplier' => 'space',
                'external_item_id' => '123',
                'supplier_sku' => '123A1',
                'ean' => '1234567890123',
                'name' => str_repeat('È', 160),
                'description' => null,
                'purchase_cost_minor' => 1000,
                'currency' => 'EUR',
                'available_quantity' => 1,
                'source_version' => 'version-1',
                'observed_at' => '2026-07-31T08:00:00+00:00',
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Campo name non valido: lunghezza 320 byte (160 caratteri), massimo consentito 255 byte.',
        );

        SpaceCatalogObservation::fromEnvelope($message);
    }
}
