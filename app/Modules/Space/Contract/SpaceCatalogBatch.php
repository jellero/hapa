<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

use InvalidArgumentException;

final readonly class SpaceCatalogBatch
{
    /** @var list<SpaceCatalogItem> */
    public array $items;

    /**
     * @param list<SpaceCatalogItem> $items
     */
    public function __construct(
        array $items,
        public SpaceCatalogCursor $nextCursor,
        public bool $hasMore,
    ) {
        foreach ($items as $item) {
            if (!$item instanceof SpaceCatalogItem) {
                throw new InvalidArgumentException('Il batch Space contiene un articolo non valido.');
            }
        }

        if ($hasMore && $items === []) {
            throw new InvalidArgumentException('Un batch Space vuoto non può dichiarare altre pagine.');
        }

        $this->items = array_values($items);
    }
}
