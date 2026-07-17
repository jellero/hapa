<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Application;

use Hapa\Modules\Space\Domain\SpaceCatalogIngestionOutcome;

final readonly class SpaceCatalogIngestionResult
{
    public function __construct(
        public int $observationId,
        public ?int $catalogItemId,
        public SpaceCatalogIngestionOutcome $outcome,
        public ?string $reason = null,
    ) {
    }
}
