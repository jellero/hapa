<?php

declare(strict_types=1);

namespace Hapa\Tests\Unit\Core;

use DateTimeImmutable;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\WebSession;
use Hapa\Core\Security\AuthorizationPolicy;
use Hapa\Core\Ui\CommercialCatalogManagement;
use Hapa\Core\Ui\OrderOverview;
use Hapa\Core\Ui\PricingPreview;
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
        self::assertStringContainsString('<h1>Accedi</h1>', (string) $response->getContent());
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
            $controller->products($request),
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

    public function testDashboardDoesNotExposeTheObsoleteRoadmap(): void
    {
        $content = (string) $this->controller()->dashboard($this->request('/ui'))->getContent();

        self::assertStringNotContainsString('Roadmap', $content);
        self::assertStringNotContainsString('Prossimi gate', $content);
        self::assertStringNotContainsString('Esplora il piano integrazioni', $content);
        self::assertStringContainsString('Stato delle capacità', $content);
        self::assertStringContainsString('data-liveness-status', $content);
        self::assertStringNotContainsString('href="/health/live"', $content);
    }

    public function testAuditExposesTheOperationalErrorFilter(): void
    {
        $content = (string) $this->controller()
            ->audit($this->request('/ui/audit?level=error'))
            ->getContent();

        self::assertStringContainsString('id="audit-level"', $content);
        self::assertStringContainsString('value="error" selected', $content);
        self::assertStringContainsString('Solo errori', $content);
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
        $shipments = (string) $controller->shipments($this->request('/ui/shipments'))->getContent();

        self::assertStringContainsString('GLS e BRT (Bartolini)', $shipments);
        self::assertStringContainsString('<th scope="col">Corriere</th>', $shipments);
        self::assertStringNotContainsString('Stato GLS', $shipments);
    }

    public function testItPresentsCommercialCatalogsAsThePrimaryWorkflow(): void
    {
        $content = (string) $this->controller()->catalog($this->request('/ui/catalog'))->getContent();

        self::assertStringContainsString('Cataloghi marketplace', $content);
        self::assertStringContainsString('Cataloghi configurati', $content);
        self::assertStringContainsString('Prodotti sorgente', $content);
        self::assertStringContainsString('catalogo nascerà in bozza', $content);
        self::assertStringNotContainsString('Nuova regola di ricarico', $content);
        self::assertStringNotContainsString('Anagrafica prodotti', $content);
    }

    public function testItPresentsProductsInADedicatedSearchPage(): void
    {
        $content = (string) $this->controller()->products($this->request('/ui/products'))->getContent();

        self::assertStringContainsString('Prodotti importati da Space', $content);
        self::assertStringContainsString('Filtri anagrafica', $content);
        self::assertStringContainsString('Disponibilità', $content);
        self::assertStringContainsString('SKU, EAN, ID Space, artista, titolo o etichetta', $content);
    }

    public function testItRendersTheRealCatalogPreviewBeforeActivation(): void
    {
        $catalogs = new class implements CommercialCatalogManagement {
            public function all(): array
            {
                return [];
            }

            public function find(int $id): array
            {
                return [
                    'id' => $id,
                    'name' => 'Catalogo anteprima',
                    'marketplace_ids' => [7],
                    'marketplace_names' => 'Temu',
                    'pricing_rule_count' => 1,
                    'include_rule_count' => 1,
                    'exclude_rule_count' => 0,
                    'eligible_product_count' => 1,
                    'ready' => true,
                    'enabled' => false,
                    'status' => 'ready',
                ];
            }

            public function preview(int $id, int $limit = 200): array
            {
                return [[
                    'id' => 91,
                    'sku' => '22658659A221',
                    'ean' => '5099703247626',
                    'name' => 'Carlos Santana - Amigos',
                    'artist' => 'Carlos Santana',
                    'title' => 'Amigos',
                    'format' => 'CD',
                    'onboarding_status' => 'approved',
                    'active' => true,
                    'sellable_quantity' => 162,
                    'available_quantity' => 162,
                    'purchase_cost_minor' => 428,
                    'currency' => 'EUR',
                    'marketplace_ids' => [7],
                ]];
            }

            public function create(array $input, UserIdentity $actor): int
            {
                return 1;
            }

            public function setEnabled(
                int $id,
                bool $enabled,
                UserIdentity $actor,
                string $correlationId,
            ): void {
            }

            public function delete(
                int $id,
                string $confirmation,
                UserIdentity $actor,
                string $correlationId,
            ): void {
            }
        };
        $prices = new class implements PricingPreview {
            public function forProducts(array $products, ?int $commercialCatalogId = null): array
            {
                return [91 => [[
                    'marketplace_id' => 7,
                    'marketplace_name' => 'Temu',
                    'selling_price_minor' => 535,
                    'currency' => 'EUR',
                    'applied_rule_code' => 'temu-default',
                ]]];
            }
        };
        $controller = new UiController(
            $this->renderer(),
            'testing',
            commercialCatalogs: $catalogs,
            pricingPreview: $prices,
        );

        $content = (string) $controller->catalog($this->request('/ui/catalog?catalog=12&preview=1'))->getContent();

        self::assertStringContainsString('Controlla cosa verrà passato', $content);
        self::assertStringContainsString('1</strong><span>prodotti selezionati', $content);
        self::assertStringContainsString('Attiva catalogo', $content);
        self::assertStringContainsString('5099703247626', $content);
        self::assertStringContainsString('22658659A221', $content);
        self::assertStringContainsString('Carlos Santana', $content);
        self::assertStringContainsString('Amigos', $content);
        self::assertStringContainsString('4,28 EUR', $content);
        self::assertStringContainsString('5,35 EUR', $content);
        self::assertStringContainsString('<option value="ean">EAN</option>', $content);
        self::assertStringContainsString('AEC ALLIANCE ENTERTAINMENT', $content);
        self::assertStringContainsString('name="match_field"', $content);
        self::assertStringNotContainsString('name="sku"', $content);
    }

    public function testItPresentsTheRealSpaceAccountConfigurationWithoutShowcaseCards(): void
    {
        $content = (string) $this->controller()->integrations($this->request('/ui/integrations'))->getContent();

        self::assertStringContainsString('hapa-automation', $content);
        self::assertStringContainsString('Mappatura campi Space', $content);
        self::assertStringContainsString('space_field_mapping[artista]', $content);
        self::assertStringContainsString('space_field_mapping[titolo]', $content);
        self::assertStringContainsString('name="space_catalog_incremental_action" value="spece"', $content);
        self::assertStringContainsString('name="space_field_mapping[idspace]" value="id_album"', $content);
        self::assertStringContainsString('name="space_field_mapping[price]" value="prezzo_vendita"', $content);
        self::assertStringNotContainsString('Percorso conferma', $content);
        self::assertStringNotContainsString('Canali, servizi e corrieri', $content);
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
