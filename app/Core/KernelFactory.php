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
use Hapa\Core\Exception\HapaRuntimeException;
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
            throw new HapaRuntimeException(sprintf('File route non trovato: %s', $routesFile));
        }

        // This configuration returns a Closure and must be evaluated for every
        // factory instance; require_once would return true after the first load.
        $routeFactory = require $routesFile; // NOSONAR
        if (!$routeFactory instanceof Closure) {
            throw new HapaRuntimeException('config/routes.php deve restituire una Closure.');
        }

        $routes = $routeFactory([
            'ui' => $this->ui, 'readiness' => $this->readiness, 'application' => $this->application,
            'authentication' => $this->authentication, 'integration_configuration' => $this->integrationConfiguration,
            'pricing_rules' => $this->pricingRules, 'catalog_review' => $this->catalogReview,
            'customers' => $this->customers, 'space_purchases' => $this->spacePurchases,
        ]);
        if (!$routes instanceof RouteCollection) {
            throw new HapaRuntimeException('La route factory deve restituire RouteCollection.');
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
