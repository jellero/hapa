<?php

declare(strict_types=1);

namespace Hapa\Core;

use Hapa\Core\Configuration\ApplicationConfig;
use Hapa\Core\Health\ReadinessCheck;
use Hapa\Core\Http\HttpResponsePolicy;
use Hapa\Core\Security\AuthorizationPolicy;
use Hapa\Core\Security\SessionManager;
use Hapa\Core\Ui\AuthenticationController;
use Hapa\Core\Ui\CatalogReviewController;
use Hapa\Core\Ui\CustomerController;
use Hapa\Core\Ui\IntegrationConfigurationController;
use Hapa\Core\Ui\PricingRuleController;
use Hapa\Core\Ui\SpacePurchaseController;
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
        private readonly AuthenticationController $authentication,
        private readonly SessionManager $sessions,
        private readonly AuthorizationPolicy $authorization,
        private readonly IntegrationConfigurationController $integrationConfiguration,
        private readonly PricingRuleController $pricingRules,
        private readonly CatalogReviewController $catalogReview,
        private readonly CustomerController $customers,
        private readonly SpacePurchaseController $spacePurchases,
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

        $routes = $routeFactory(
            $this->ui,
            $this->readiness,
            $this->application,
            $this->authentication,
            $this->integrationConfiguration,
            $this->pricingRules,
            $this->catalogReview,
            $this->customers,
            $this->spacePurchases,
        );
        if (!$routes instanceof RouteCollection) {
            throw new RuntimeException('La route factory deve restituire RouteCollection.');
        }

        return new Kernel(
            $routes,
            $this->logger,
            $this->application->debug,
            $this->responsePolicy,
            $this->sessions,
            $this->authorization,
        );
    }
}
