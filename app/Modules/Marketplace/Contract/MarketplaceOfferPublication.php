<?php

declare(strict_types=1);

namespace Hapa\Modules\Marketplace\Contract;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MarketplaceOfferPublication
{
    public function __construct(
        public string $externalOfferId,
        public ?string $remoteVersion,
        public DateTimeImmutable $acceptedAt,
    ) {
        if (trim($externalOfferId) !== $externalOfferId || $externalOfferId === '' || strlen($externalOfferId) > 160) {
            throw new InvalidArgumentException('L’identificativo remoto dell’offerta è obbligatorio.');
        }

        if ($remoteVersion !== null && (
            trim($remoteVersion) !== $remoteVersion
            || $remoteVersion === ''
            || strlen($remoteVersion) > 160
        )) {
            throw new InvalidArgumentException('La versione remota dell’offerta non può essere vuota.');
        }
    }
}
