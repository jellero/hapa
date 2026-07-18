<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Ui\SpacePurchaseController;
use Hapa\Core\Ui\SpacePurchaseManagement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SpacePurchaseControllerTest extends TestCase
{
    public function testItGeneratesThePurchaseAndReturnsToTheOrder(): void
    {
        $management = new class implements SpacePurchaseManagement {
            public ?string $orderNumber = null;

            public function generateForOrder(string $orderNumber, string $correlationId): void
            {
                $this->orderNumber = $orderNumber;
            }

            public function generateOutstanding(string $correlationId, int $limit = 500): array
            {
                return ['examined' => 0, 'generated' => 0, 'manual_review' => 0, 'failed' => 0];
            }
        };
        $request = Request::create('/ui/orders/HAPA-42/space-purchase', 'POST');
        $request->attributes->set('orderId', 'hapa-42');
        $request->attributes->set('correlation_id', 'purchase-test-correlation');

        $response = (new SpacePurchaseController($management))->generate($request);

        self::assertSame('HAPA-42', $management->orderNumber);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/ui/orders/HAPA-42?purchase_generated=1#purchases', $response->headers->get('Location'));
    }

    public function testItReturnsTheOperationalReasonToTheOrder(): void
    {
        $management = new class implements SpacePurchaseManagement {
            public function generateForOrder(string $orderNumber, string $correlationId): void
            {
                throw new InvalidArgumentException('Ordine HAPA non trovato.');
            }

            public function generateOutstanding(string $correlationId, int $limit = 500): array
            {
                return ['examined' => 0, 'generated' => 0, 'manual_review' => 0, 'failed' => 0];
            }
        };
        $request = Request::create('/ui/orders/HAPA-404/space-purchase', 'POST');
        $request->attributes->set('orderId', 'HAPA-404');

        $response = (new SpacePurchaseController($management))->generate($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('purchase_error=Ordine%20HAPA%20non%20trovato.', (string) $response->headers->get('Location'));
    }
}
