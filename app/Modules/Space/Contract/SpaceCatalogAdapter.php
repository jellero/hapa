<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

interface SpaceCatalogAdapter
{
    public function fetchCatalogChanges(SpaceCatalogCursor $cursor, int $limit): SpaceCatalogBatch;
}
