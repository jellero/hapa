<?php

declare(strict_types=1);

namespace Hapa\Core;

use Hapa\Core\Configuration\Environment;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Health\ReadinessCheck;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

final readonly class Bootstrap
{
    public function __construct(
        public Environment $environment,
        public ReadinessCheck $readiness,
    ) {
    }

    public static function initialize(string $basePath): self
    {
        if (is_file($basePath . '/.env')) {
            (new Dotenv())->usePutenv()->loadEnv($basePath . '/.env');
        }

        $environment = Environment::load();
        date_default_timezone_set($environment->timezone);

        if ($environment->trustedProxies !== []) {
            Request::setTrustedProxies(
                $environment->trustedProxies,
                Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
            );
        }

        return new self(
            $environment,
            new ReadinessCheck(new ConnectionFactory()),
        );
    }
}
