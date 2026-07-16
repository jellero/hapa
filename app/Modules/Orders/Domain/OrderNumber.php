<?php

declare(strict_types=1);

namespace Hapa\Modules\Orders\Domain;

use InvalidArgumentException;
use Stringable;

final readonly class OrderNumber implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $normalized = strtoupper(trim($value));
        if (!preg_match('/^[A-Z0-9][A-Z0-9._-]{2,63}$/D', $normalized)) {
            throw new InvalidArgumentException(
                'Il numero ordine deve contenere da 3 a 64 caratteri alfanumerici, punto, trattino o underscore.',
            );
        }

        $this->value = $normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
