<?php

declare(strict_types=1);

namespace Hapa\Composition;

use Hapa\Core\Database\PdoTransactionManager;
use Hapa\Core\Messaging\InboundMessageHandlerRegistry;
use Hapa\Core\Messaging\InboundMessageHandlerRegistryFactory;
use Hapa\Core\Messaging\TransportProbeHandler;
use Hapa\Modules\Space\Application\SpaceCatalogInboundHandler;
use Hapa\Modules\Space\Application\SpaceCatalogObservationHandler;
use PDO;

final readonly class HapaInboundMessageHandlerRegistryFactory implements InboundMessageHandlerRegistryFactory
{
    public function create(PDO $connection): InboundMessageHandlerRegistry
    {
        return new InboundMessageHandlerRegistry([
            new TransportProbeHandler(),
            new SpaceCatalogInboundHandler(new SpaceCatalogObservationHandler(
                $connection,
                new PdoTransactionManager($connection),
            )),
        ]);
    }
}
