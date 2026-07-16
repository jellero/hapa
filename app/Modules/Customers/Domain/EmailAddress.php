<?php

declare(strict_types=1);

namespace Hapa\Modules\Customers\Domain;

use InvalidArgumentException;
use Stringable;

final readonly class EmailAddress implements Stringable
{
    public string $value;
    public string $normalized;

    public function __construct(string $value)
    {
        $email = trim($value);
        if (
            $email === ''
            || strlen($email) > 254
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidArgumentException('L’indirizzo email cliente non è valido.');
        }

        $this->value = $email;
        $this->normalized = strtolower($email);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
