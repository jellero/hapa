<?php

declare(strict_types=1);

namespace Hapa\Core;

use RuntimeException;
use Symfony\Component\Routing\RouteCollection;

final class KernelFactory
{
    public function create(string $basePath): Kernel
    {
        $routesFile = $basePath . '/config/routes.php';

        if (!is_file($routesFile)) {
            throw new RuntimeException(sprintf('File route non trovato: %s', $routesFile));
        }

        $routes = require $routesFile;

        if (!$routes instanceof RouteCollection) {
            throw new RuntimeException('config/routes.php deve restituire RouteCollection.');
        }

        return new Kernel($routes);
    }
}
