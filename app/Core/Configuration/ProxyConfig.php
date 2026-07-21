<?php

declare(strict_types=1);

namespace Hapa\Core\Configuration;

use Hapa\Core\Exception\HapaRuntimeException;

final readonly class ProxyConfig
{
    /** @var list<string> */
    public array $trustedProxies;

    /** @param list<string> $trustedProxies */
    public function __construct(array $trustedProxies)
    {
        $normalized = [];
        foreach ($trustedProxies as $proxy) {
            $value = trim($proxy);
            if ($value === '' || !preg_match('/^[A-Za-z0-9_.:\/-]+$/D', $value)) {
                throw new HapaRuntimeException('TRUSTED_PROXIES contiene un valore non valido.');
            }

            $normalized[] = $value;
        }

        $this->trustedProxies = array_values(array_unique($normalized));
    }
}
