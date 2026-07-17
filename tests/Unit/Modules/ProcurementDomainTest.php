<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Modules;

use Hapa\Modules\Orders\Domain\OrderStatus;
use Hapa\Modules\Procurement\Domain\SupplierCode;
use Hapa\Modules\Procurement\Domain\SupplierPurchaseOrderStatus;
use PHPUnit\Framework\TestCase;

final class ProcurementDomainTest extends TestCase
{
    public function testSpaceIsAnExplicitSupplier(): void
    {
        self::assertSame('space', SupplierCode::Space->value);
    }

    public function testPurchaseStatusesAreIndependentFromSalesStatuses(): void
    {
        $purchaseStatuses = array_map(
            static fn (SupplierPurchaseOrderStatus $status): string => $status->value,
            SupplierPurchaseOrderStatus::cases(),
        );
        $salesStatuses = array_map(static fn (OrderStatus $status): string => $status->value, OrderStatus::cases());

        self::assertContains('requested', $purchaseStatuses);
        self::assertContains('accepted', $purchaseStatuses);
        self::assertContains('accepted', $salesStatuses);
        self::assertNotContains('requested', $salesStatuses);
    }
}
