<?php

declare(strict_types=1);

namespace Hapa\Composition;

use Hapa\Core\Clock\SystemClock;
use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Messaging\InboundMessageHandlerRegistry;
use Hapa\Core\Messaging\InboundMessageHandlerRegistryFactory;
use Hapa\Core\Messaging\TransportProbeHandler;
use Hapa\Core\Outbox\PostgresOutboxRepository;
use Hapa\Core\Outbox\ProviderCommandFactory;
use Hapa\Core\Outbox\ProviderCommandPayloadValidator;
use Hapa\Modules\Orders\Application\OrderEventOutboxMapper;
use Hapa\Modules\Orders\Application\MarketplaceOrderInboundHandler;
use Hapa\Modules\Orders\Application\MarketplaceOrderObservationHandler;
use Hapa\Modules\Orders\Infrastructure\Persistence\PostgresOrderRepository;
use Hapa\Modules\Procurement\Application\AutomaticSpacePurchaseGenerator;
use Hapa\Modules\Procurement\Application\SpacePurchaseOrderResultHandler;
use Hapa\Modules\Space\Application\SpaceCatalogInboundHandler;
use Hapa\Modules\Space\Application\SpaceCatalogObservationHandler;
use PDO;

final readonly class HapaInboundMessageHandlerRegistryFactory implements InboundMessageHandlerRegistryFactory
{
    public function create(PDO $connection): InboundMessageHandlerRegistry
    {
        $transactions = new PdoTransactionManager($connection);
        $outbox = new PostgresOutboxRepository($connection);
        $orders = new PostgresOrderRepository(
            $connection,
            $transactions,
            $outbox,
            new OrderEventOutboxMapper(),
        );
        $spacePurchases = new AutomaticSpacePurchaseGenerator(
            $connection,
            $outbox,
            new ProviderCommandFactory(new ProviderCommandPayloadValidator(), new SystemClock()),
        );

        return new InboundMessageHandlerRegistry([
            new TransportProbeHandler(),
            new SpaceCatalogInboundHandler(new SpaceCatalogObservationHandler(
                $connection,
                $transactions,
            )),
            new SpacePurchaseOrderResultHandler($connection),
            new MarketplaceOrderInboundHandler(new MarketplaceOrderObservationHandler(
                $connection,
                $transactions,
                $orders,
                $spacePurchases,
            )),
        ]);
    }
}
