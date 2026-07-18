<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use DateTimeImmutable;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\WebSession;
use Hapa\Core\Security\AuthorizationPolicy;
use Hapa\Core\Ui\OrderOverview;
use Hapa\Core\Ui\UiController;
use Hapa\Core\View\ViewRenderer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UiControllerTest extends TestCase
{
    public function testItRendersTheActiveCredentialForm(): void
    {
        $response = $this->controller()->login($this->request('/login'));

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('<h2>Accedi a HAPA</h2>', (string) $response->getContent());
        self::assertStringContainsString('action="/login" method="post"', (string) $response->getContent());
        self::assertStringNotContainsString('<fieldset disabled>', (string) $response->getContent());
    }

    public function testItRendersEveryHapaOperationalArea(): void
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
            $controller->integrations($request),
            $controller->users($request),
            $controller->audit($request),
            $controller->settings($request),
            $controller->profile($request),
        ];

        foreach ($responses as $response) {
            self::assertSame(Response::HTTP_OK, $response->getStatusCode());
            self::assertStringContainsString('data-ui-shell', (string) $response->getContent());
            self::assertStringContainsString('Sessione protetta attiva', (string) $response->getContent());
        }
    }

    public function testItEscapesSearchInputInCollectionPages(): void
    {
        $request = $this->request('/ui/orders?q=%3Cscript%3Ealert(1)%3C%2Fscript%3E');
        $content = (string) $this->controller()->orders($request)->getContent();

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

    public function testItPresentsTheProductRegistryAndMarkupFlow(): void
    {
        $content = (string) $this->controller()->catalog($this->request('/ui/catalog'))->getContent();

        self::assertStringContainsString('Anagrafica prodotti, prezzi e stock', $content);
        self::assertStringContainsString('Space sincronizza prezzo e stock del prodotto', $content);
        self::assertStringContainsString('Nuova regola di ricarico', $content);
        self::assertStringContainsString('Marketplace + SKU', $content);
        self::assertStringContainsString('hapa-automation', $content);
        self::assertStringNotContainsString('HAPA applica scorta di sicurezza', $content);
    }

    public function testItPresentsAutomationAsASeparateIntegration(): void
    {
        $content = (string) $this->controller()->integrations($this->request('/ui/integrations'))->getContent();

        self::assertStringContainsString('hapa-automation', $content);
        self::assertStringContainsString('RabbitMQ', $content);
        self::assertStringContainsString('database proprio', $content);
        self::assertStringContainsString('SellRapido', $content);
        self::assertStringContainsString('import ordini IBS', $content);
        self::assertStringContainsString('Operativo', $content);
    }

    public function testItIgnoresUnknownCollectionFilters(): void
    {
        $request = $this->request('/ui/orders?status=not-a-real-status');
        $content = (string) $this->controller()->orders($request)->getContent();

        self::assertStringNotContainsString('value="not-a-real-status" selected', $content);
        self::assertStringContainsString('<option value="">Tutti gli stati</option>', $content);
    }

    public function testItEscapesTheOrderIdentifier(): void
    {
        $request = $this->request('/ui/orders/example');
        $request->attributes->set('orderId', '<img src=x onerror=alert(1)>');
        $content = (string) $this->controller()->orderDetail($request)->getContent();

        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $content);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $content);
    }

    public function testItExposesTheSpacePurchaseActionOnAnImportedOrder(): void
    {
        $orders = new class implements OrderOverview {
            public function search(string $query, string $status, int $limit = 100): array
            {
                return [];
            }

            public function detail(string $orderNumber): array
            {
                return [
                    'order_number' => $orderNumber,
                    'status' => 'imported',
                    'customer_name' => 'Cliente test',
                    'customer_code' => 'C-1',
                    'marketplace_name' => 'IBS',
                    'marketplace_account_name' => 'SellRapido IBS',
                    'origin_reference' => 'IBS-1',
                    'origin' => 'marketplace',
                    'ordered_at' => '2026-07-18T10:00:00Z',
                    'updated_at' => '2026-07-18T10:00:00Z',
                    'grand_total_minor' => 1000,
                    'subtotal_minor' => 1000,
                    'shipping_total_minor' => 0,
                    'discount_total_minor' => 0,
                    'tax_total_minor' => 0,
                    'currency' => 'EUR',
                    'version' => 1,
                    'external_order_id' => 'IBS-1',
                    'connector_code' => 'sellrapido',
                    'customer_email' => 'cliente@example.test',
                    'customer_phone' => null,
                    'lines' => [],
                    'purchases' => [],
                    'shipments' => [],
                    'legacy_deliveries' => [],
                    'shipping_address' => null,
                    'billing_address' => null,
                    'transitions' => [],
                ];
            }
        };
        $controller = new UiController(
            $this->renderer(),
            'testing',
            orderReadModel: $orders,
            authorization: new AuthorizationPolicy(),
        );
        $request = $this->request('/ui/orders/HAPA-1');
        $request->attributes->set('orderId', 'HAPA-1');

        $content = (string) $controller->orderDetail($request)->getContent();

        self::assertStringContainsString('action="/ui/orders/HAPA-1/space-purchase"', $content);
        self::assertStringContainsString('Genera acquisto Space', $content);
        self::assertStringContainsString(hash_hmac('sha256', 'order.space-purchase.HAPA-1', 'test-session-token'), $content);
    }

    public function testItEscapesTheCustomerIdentifier(): void
    {
        $request = $this->request('/ui/customers/example');
        $request->attributes->set('customerId', '<img src=x onerror=alert(1)>');
        $content = (string) $this->controller()->customerDetail($request)->getContent();

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
        return new UiController($this->renderer(), 'testing');
    }

    private function renderer(): ViewRenderer
    {
        return new ViewRenderer(dirname(__DIR__, 3) . '/templates');
    }

    private function request(string $uri): Request
    {
        $request = Request::create($uri);
        $request->attributes->set('correlation_id', 'test-correlation-id');
        $user = new UserIdentity('test-user', 'admin@example.test', 'Test Administrator', 'administrator');
        $request->attributes->set('current_user', $user);
        $request->attributes->set('security_session', new WebSession('test-session-token', $user, new DateTimeImmutable('+1 hour')));

        return $request;
    }
}
