<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use Hapa\Core\Automation\AutomationCatalog;
use Hapa\Core\Ui\UiController;
use Hapa\Core\View\ViewRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UiControllerTest extends TestCase
{
    public function testItRendersTheLoginInterfaceWithoutAnActiveCredentialForm(): void
    {
        $response = $this->controller()->login($this->request('/login'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<h2>Accedi a HAPA</h2>', (string) $response->getContent());
        self::assertStringContainsString('<fieldset disabled>', (string) $response->getContent());
    }

    public function testItRendersEveryOperationalArea(): void
    {
        $controller = $this->controller();
        $request = $this->request('/ui');
        $responses = [
            $controller->dashboard($request),
            $controller->customers($request),
            $controller->orders($request),
            $controller->catalog($request),
            $controller->picking($request),
            $controller->shipments($request),
            $controller->automation($request),
            $controller->integrations($request),
            $controller->users($request),
            $controller->audit($request),
            $controller->settings($request),
            $controller->profile($request),
        ];

        foreach ($responses as $response) {
            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertStringContainsString('data-ui-shell', (string) $response->getContent());
            self::assertStringContainsString('Interfaccia pronta, dati non ancora collegati', (string) $response->getContent());
        }
    }

    public function testItEscapesSearchInputInCollectionPages(): void
    {
        $request = $this->request('/ui/orders?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E');
        $response = $this->controller()->orders($request);
        $content = (string) $response->getContent();

        self::assertStringNotContainsString('<script>alert(1)</script>', $content);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $content);
    }

    public function testItPresentsBrtAndProviderNeutralShipmentCopy(): void
    {
        $controller = $this->controller();

        $integrations = (string) $controller->integrations($this->request('/ui/integrations'))->getContent();
        $shipments = (string) $controller->shipments($this->request('/ui/shipments'))->getContent();

        self::assertStringContainsString('BRT (Bartolini)', $integrations);
        self::assertStringContainsString('GLS e BRT (Bartolini)', $shipments);
        self::assertStringContainsString('<th scope="col">Corriere</th>', $shipments);
        self::assertStringNotContainsString('Stato GLS', $shipments);
    }

    public function testItPresentsTheConcreteAutomationPlanAndRuntime(): void
    {
        $content = (string) $this->controller()->automation($this->request('/ui/automation'))->getContent();

        self::assertStringContainsString('8 job censiti', $content);
        self::assertStringContainsString('Accetta ordini completi', $content);
        self::assertStringContainsString('Esporta verso Space', $content);
        self::assertStringContainsString('Sincronizza catalogo Space', $content);
        self::assertStringContainsString('Pubblica offerte marketplace', $content);
        self::assertStringContainsString('Gestisci parziali confermati', $content);
        self::assertStringContainsString('bin/console automation:run', $content);
        self::assertStringContainsString('BRT', $content);
    }

    public function testItPresentsSpacePricingAndMarketplaceAvailabilityFlow(): void
    {
        $content = (string) $this->controller()->catalog($this->request('/ui/catalog'))->getContent();

        self::assertStringContainsString('Catalogo, prezzi e disponibilità', $content);
        self::assertStringContainsString('Space fornisce prezzo base e disponibilità fisica', $content);
        self::assertStringContainsString('Motore ricarichi', $content);
        self::assertStringContainsString('Marketplace + SKU', $content);
        self::assertStringContainsString('Adapter spenti', $content);
    }

    public function testItIgnoresUnknownCollectionFilters(): void
    {
        $request = $this->request('/ui/orders?status=not-a-real-status');
        $response = $this->controller()->orders($request);
        $content = (string) $response->getContent();

        self::assertStringNotContainsString('value="not-a-real-status" selected', $content);
        self::assertStringContainsString('<option value="Tutti gli stati">Tutti gli stati</option>', $content);
    }

    public function testItEscapesTheOrderIdentifier(): void
    {
        $request = $this->request('/ui/orders/example');
        $request->attributes->set('orderId', '<img src=x onerror=alert(1)>');
        $response = $this->controller()->orderDetail($request);
        $content = (string) $response->getContent();

        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $content);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $content);
    }

    public function testItEscapesTheCustomerIdentifier(): void
    {
        $request = $this->request('/ui/customers/example');
        $request->attributes->set('customerId', '<img src=x onerror=alert(1)>');
        $response = $this->controller()->customerDetail($request);
        $content = (string) $response->getContent();

        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $content);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $content);
    }

    public function testRendererRejectsTemplateTraversal(): void
    {
        $this->expectException(RuntimeException::class);

        $this->renderer()->render('../secrets');
    }

    public function testItRendersABrandedNotFoundPage(): void
    {
        $response = $this->controller()->notFound($this->request('/ui/not-found'));

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        self::assertStringContainsString('Pagina non trovata', (string) $response->getContent());
    }

    private function controller(): UiController
    {
        return new UiController($this->renderer(), 'testing', new AutomationCatalog());
    }

    private function renderer(): ViewRenderer
    {
        return new ViewRenderer(dirname(__DIR__, 3) . '/templates');
    }

    private function request(string $uri): Request
    {
        $request = Request::create($uri);
        $request->attributes->set('correlation_id', 'test-correlation-id');

        return $request;
    }
}
