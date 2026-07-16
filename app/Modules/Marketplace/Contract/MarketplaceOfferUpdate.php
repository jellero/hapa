<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use Hapa\Modules\Catalog\Contract\Money;
use InvalidArgumentException;

final readonly class MarketplaceOfferUpdate
{
    public function __construct(
        public MarketplaceChannel $channel,
        public string $sku,
        public ?string $externalOfferId,
        public Money $price,
        public int $availableQuantity,
        public string $sourceVersion,
    ) {
        if (trim($sku) !== $sku || $sku === '' || strlen($sku) > 160) {
            throw new InvalidArgumentException('Lo SKU dell’offerta marketplace è obbligatorio.');
        }

        if ($externalOfferId !== null && (
            trim($externalOfferId) !== $externalOfferId
            || $externalOfferId === ''
            || strlen($externalOfferId) > 160
        )) {
            throw new InvalidArgumentException('L’identificativo offerta marketplace non può essere vuoto.');
        }

        if ($availableQuantity < 0) {
            throw new InvalidArgumentException('La disponibilità pubblicabile non può essere negativa.');
        }

        if (trim($sourceVersion) !== $sourceVersion || $sourceVersion === '' || strlen($sourceVersion) > 160) {
            throw new InvalidArgumentException('La versione HAPA dell’offerta è obbligatoria.');
        }
    }
}
