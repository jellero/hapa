<?php

declare(strict_types=1);

namespace Hapa\Core;

use Hapa\Core\Bootstrap\ApplicationContext;
use RuntimeException;
use Symfony\Component\Routing\RouteCollection;

final class KernelFactory
{
    public function create(string $basePath, ApplicationContext $context): Kernel
    {
        $routesFile = $basePath . '/config/routes.php';

        if (!is_file($routesFile)) {
            throw new RuntimeException(sprintf('File route non trovato: %s', $routesFile));
        }

        $routeFactory = require $routesFile;
        if (!is_callable($routeFactory)) {
            throw new RuntimeException('config/routes.php deve restituire una factory callable.');
        }

        $routes = $routeFactory($context);
        if (!$routes instanceof RouteCollection) {
            throw new RuntimeException('La factory delle route deve restituire RouteCollection.');
        }

        return new Kernel(
            $routes,
            $context->logger,
            $context->environment->debug,
        );
    }
}
