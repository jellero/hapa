<?php

declare(strict_types=1);

namespace Hapa\Modules\Catalog\Contract;

use PDO;

interface CatalogOfferRecalculator
{
    public function recalculateProduct(PDO $pdo, int $catalogItemId): int;

    public function recalculateAll(PDO $pdo): int;
}
