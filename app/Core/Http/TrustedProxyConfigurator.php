<?php

declare(strict_types=1);

namespace Hapa\Core\Http;

use Hapa\Core\Configuration\Environment;
use Hapa\Core\Configuration\EnvironmentReader;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

final class TrustedProxyConfigurator
{
    public function configure(Environment $environment): void
    {
        $proxies = EnvironmentReader::commaSeparated(
            'TRUSTED_PROXIES',
            $environment->isProduction() ? 'REMOTE_ADDR' : '',
        );

        if ($proxies !== []) {
            Request::setTrustedProxies(
                $proxies,
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PREFIX,
            );
        }

        if (!$environment->isProduction()) {
            return;
        }

        $host = parse_url($environment->appUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new RuntimeException('APP_URL deve contenere un host valido.');
        }

        Request::setTrustedHosts(['^' . preg_quote($host, '{') . '$']);
    }
}
