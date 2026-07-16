<?php

declare(strict_types=1);

namespace Hapa\Modules\Space\Contract;

use InvalidArgumentException;

final readonly class SpaceCatalogCursor
{
    public function __construct(public ?string $token = null)
    {
        if ($token !== null && (trim($token) === '' || strlen($token) > 512)) {
            throw new InvalidArgumentException('Il cursore catalogo Space non è valido.');
        }
    }
}
