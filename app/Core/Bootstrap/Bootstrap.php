<?php

declare(strict_types=1);

namespace Hapa\Core\Bootstrap;

use Hapa\Core\Configuration\Environment;
use Hapa\Core\Database\ConnectionFactory;
use Hapa\Core\Database\SchemaVersion;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Http\TrustedProxyConfigurator;
use Hapa\Core\Logging\LoggerFactory;
use Symfony\Component\Dotenv\Dotenv;

final class Bootstrap
{
    public function initialize(string $basePath): ApplicationContext
    {
        $environmentFile = $basePath . '/.env';
        if (is_file($environmentFile)) {
            (new Dotenv())->usePutenv()->loadEnv($environmentFile);
        }

        $environment = Environment::load();
        date_default_timezone_set($environment->timezone);
        (new TrustedProxyConfigurator())->configure($environment);

        $logger = (new LoggerFactory())->create();
        $connections = new ConnectionFactory();
        $readiness = new ReadinessCheck($connections, $logger, SchemaVersion::LATEST);

        return new ApplicationContext(
            $environment,
            $logger,
            $connections,
            $readiness,
        );
    }
}
