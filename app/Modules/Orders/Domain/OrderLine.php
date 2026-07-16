<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

final readonly class OrderLine
{
    public string $sku;
    public ?string $externalLineId;
    public ?string $ean;

    public function __construct(
        public int $lineNumber,
        string $sku,
        ?string $externalLineId,
        ?string $ean,
        public int $quantityOrdered,
        public int $quantityAvailable = 0,
        public int $quantityToShip = 0,
        public int $quantityToCancel = 0,
    ) {
        if ($lineNumber < 1) {
            throw new OrderDomainException('Il numero riga deve essere positivo.');
        }

        $this->sku = self::required($sku, 'SKU', 160);
        $this->externalLineId = self::optional($externalLineId, 'ID riga esterno', 160);
        $this->ean = self::optional($ean, 'EAN', 32);

        if ($quantityOrdered < 1) {
            throw new OrderDomainException('La quantità ordinata deve essere positiva.');
        }

        if ($quantityAvailable < 0 || $quantityToShip < 0 || $quantityToCancel < 0) {
            throw new OrderDomainException('Le quantità della riga non possono essere negative.');
        }

        if ($quantityAvailable > $quantityOrdered) {
            throw new OrderDomainException('La quantità disponibile non può superare quella ordinata.');
        }

        if ($quantityToShip > $quantityAvailable) {
            throw new OrderDomainException('La quantità da spedire non può superare quella disponibile.');
        }

        if ($quantityToShip + $quantityToCancel > $quantityOrdered) {
            throw new OrderDomainException('La somma tra quantità da spedire e annullare supera quella ordinata.');
        }
    }

    public function withAvailability(int $quantityAvailable): self
    {
        return new self(
            $this->lineNumber,
            $this->sku,
            $this->externalLineId,
            $this->ean,
            $this->quantityOrdered,
            $quantityAvailable,
        );
    }

    public function withFullFulfilment(): self
    {
        if (!$this->isFullyAvailable()) {
            throw new OrderDomainException('Una riga non completamente disponibile non può essere pianificata come completa.');
        }

        return new self(
            $this->lineNumber,
            $this->sku,
            $this->externalLineId,
            $this->ean,
            $this->quantityOrdered,
            $this->quantityAvailable,
            $this->quantityOrdered,
        );
    }

    public function withDecision(int $quantityToShip, int $quantityToCancel): self
    {
        if ($quantityToShip + $quantityToCancel !== $this->quantityOrdered) {
            throw new OrderDomainException('La decisione deve coprire l’intera quantità ordinata.');
        }

        return new self(
            $this->lineNumber,
            $this->sku,
            $this->externalLineId,
            $this->ean,
            $this->quantityOrdered,
            $this->quantityAvailable,
            $quantityToShip,
            $quantityToCancel,
        );
    }

    public function isFullyAvailable(): bool
    {
        return $this->quantityAvailable === $this->quantityOrdered;
    }

    public function isFulfilmentPlanned(): bool
    {
        return $this->quantityToShip + $this->quantityToCancel === $this->quantityOrdered;
    }

    public function isPartial(): bool
    {
        return $this->quantityToCancel > 0;
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
}
