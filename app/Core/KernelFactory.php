<?php

declare(strict_types=1);

namespace Hapa\Core;

use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Http\HttpResponsePolicy;
use Hapa\Core\Ui\UiController;
use Closure;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Routing\RouteCollection;

final class KernelFactory
{
    public function __construct(
        private readonly UiController $ui,
        private readonly ReadinessCheck $readiness,
        private readonly ApplicationConfig $application,
        private readonly LoggerInterface $logger,
        private readonly HttpResponsePolicy $responsePolicy,
    ) {
    }

    public function create(string $basePath): Kernel
    {
        $routesFile = $basePath . '/config/routes.php';
        if (!is_file($routesFile)) {
            throw new RuntimeException(sprintf('File route non trovato: %s', $routesFile));
        }

        $routeFactory = require $routesFile;
        if (!$routeFactory instanceof Closure) {
            throw new RuntimeException('config/routes.php deve restituire una Closure.');
        }

        $routes = $routeFactory($this->ui, $this->readiness, $this->application);
        if (!$routes instanceof RouteCollection) {
            throw new RuntimeException('La route factory deve restituire RouteCollection.');
        }

        return new Kernel(
            $routes,
            $this->logger,
            $this->application->debug,
            $this->responsePolicy,
        );
    }
}
