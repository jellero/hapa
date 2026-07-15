<?php

declare(strict_types=1);

namespace Hapa\Core;

use Hapa\Core\Logging\LoggerFactory;
use Closure;
use RuntimeException;
use Symfony\Component\Routing\RouteCollection;

final class KernelFactory
{
    public function create(string $basePath, Bootstrap $bootstrap): Kernel
    {
        $routesFile = $basePath . '/config/routes.php';
        if (!is_file($routesFile)) {
            throw new RuntimeException(sprintf('File route non trovato: %s', $routesFile));
        }

        $routeFactory = require $routesFile;
        if (!$routeFactory instanceof Closure) {
            throw new RuntimeException('config/routes.php deve restituire una Closure.');
        }

        $routes = $routeFactory($bootstrap);
        if (!$routes instanceof RouteCollection) {
            throw new RuntimeException('La route factory deve restituire RouteCollection.');
        }

        return new Kernel(
            $routes,
            (new LoggerFactory())->create(),
            $bootstrap->environment->debug,
        );
    }
}
