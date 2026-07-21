<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

use DateTimeImmutable;
use Hapa\Modules\Orders\Domain\Event\OrderAddressChanged;
use Hapa\Modules\Orders\Domain\Event\OrderAvailabilityChanged;
use Hapa\Modules\Orders\Domain\Event\OrderStatusChanged;

trait OrderValidation
{
    private static function assertSource(
        OrderOrigin $origin,
        ?int $marketplaceId,
        ?string $originReference,
    ): void {
        if (
            $origin === OrderOrigin::Marketplace
            && ($marketplaceId === null || $marketplaceId < 1 || $originReference !== null)
        ) {
            throw new OrderDomainException('Un ordine marketplace richiede marketplace e vieta il riferimento storefront.');
        }

        if (
            $origin === OrderOrigin::B2cEcommerce
            && ($marketplaceId !== null || $originReference === null)
        ) {
            throw new OrderDomainException('Un ordine B2C richiede il riferimento storefront e vieta il marketplace.');
    }

}

    private static function initialStatus(OrderOrigin $origin): OrderStatus
    {
        return match ($origin) {
            OrderOrigin::Marketplace => OrderStatus::Imported,
            OrderOrigin::B2cEcommerce => OrderStatus::New,
        };
    }

    private static function required(string $value, string $field, int $maximumLength): string
    {
        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > $maximumLength) {
            throw new OrderDomainException(sprintf(
                'Il campo %s è obbligatorio e non può superare %d caratteri.',
                $field,
                $maximumLength,
            ));
        }

        return $normalized;
    }

    private static function optional(?string $value, string $field, int $maximumLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '' || strlen($normalized) > $maximumLength) {
            throw new OrderDomainException(sprintf(
                'Il campo %s non può essere vuoto o superare %d caratteri.',
                $field,
                $maximumLength,
            ));
        }

        return $normalized;
    }

    private static function reason(string $reason): string
    {
        $normalized = trim($reason);
        if ($normalized === '' || strlen($normalized) > 255) {
            throw new OrderDomainException('La motivazione è obbligatoria e non può superare 255 caratteri.');
        }

        return $normalized;
    }
}
