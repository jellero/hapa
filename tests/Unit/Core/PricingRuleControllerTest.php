<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Ui\PricingRuleController;
use Hapa\Core\Ui\PricingRuleManagement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class PricingRuleControllerTest extends TestCase
{
    public function testItConvertsAnOperatorPercentageToBasisPoints(): void
    {
        $rules = new class implements PricingRuleManagement {
            /** @var array<string, mixed> */
            public array $created = [];

            public function all(?int $commercialCatalogId = null): array
            {
                return [];
            }

            public function marketplaces(): array
            {
                return [];
            }

            public function create(array $input, UserIdentity $actor, string $correlationId): int
            {
                $this->created = $input;
                return 1;
            }

            public function update(
                int $id,
                int $expectedVersion,
                array $input,
                UserIdentity $actor,
                string $correlationId,
            ): void {
            }

            public function retire(
                int $id,
                int $expectedVersion,
                UserIdentity $actor,
                string $correlationId,
            ): void {
            }
        };
        $request = Request::create('/ui/catalog/pricing-rules', 'POST', [
            'commercial_catalog_id' => '1',
            'code' => 'temu-default',
            'name' => 'Ricarico Temu',
            'scope' => 'marketplace',
            'marketplace_id' => '2',
            'adjustment_type' => 'percentage',
            'percentage_value' => '20',
            'currency' => 'EUR',
            'priority' => '100',
            'enabled' => '1',
        ]);
        $request->attributes->set(
            'current_user',
            new UserIdentity('operator', 'operator@example.test', 'Operator', 'administrator'),
        );
        $request->attributes->set('correlation_id', 'pricing-percentage-test');

        $response = (new PricingRuleController($rules))->create($request);

        self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
        self::assertSame('2000', $rules->created['adjustment_value']);
        self::assertSame('percentage', $rules->created['adjustment_type']);
        self::assertSame('marketplace', $rules->created['scope']);
    }
}
