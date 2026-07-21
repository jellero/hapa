<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\Audit\AuditReadModel;
use Hapa\Core\Observability\RuntimeOverview;
use Hapa\Core\Security\AuthorizationPolicy;
use Hapa\Core\Security\UserIdentity;
use Hapa\Core\Security\WebSession;
use Hapa\Core\Integration\IntegrationAccountConfiguration;
use Hapa\Core\Integration\IntegrationAccountRepository;
use Hapa\Core\Integration\ProviderSecretFields;
use Hapa\Core\View\ViewRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class UiController
{
    use UiControllerSupport;
    private const MARKETPLACE_CHANNEL_KIND = 'Canale · ordini, prezzi e stock';
    private const UNAVAILABLE = 'Non disponibile';

    public function __construct(
        private ViewRenderer $views,
        private string $environment,
        private ?CatalogOverview $catalogReadModel = null,
        private ?IntegrationAccountRepository $integrationAccounts = null,
        private ?IntegrationAccountConfiguration $integrationConfiguration = null,
        private ?AuditReadModel $auditReadModel = null,
        private ?RuntimeOverview $runtimeOverview = null,
        private ?PricingRuleManagement $pricingRules = null,
        private ?CustomerOverview $customerReadModel = null,
        private ?OrderOverview $orderReadModel = null,
        private ?AuthorizationPolicy $authorization = null,
        private ?PricingPreview $pricingPreview = null,
        private ?ShipmentOverview $shipmentReadModel = null,
        private ?ProviderSecretFields $providerSecretFields = null,
    ) {
    }

    public function login(Request $request): Response
    {
        $session = $request->attributes->get('security_session');
        $next = $request->query->getString('next', $request->request->getString('next', '/ui'));
        if (!str_starts_with($next, '/ui') || str_starts_with($next, '//')) {
            $next = '/ui';
        }

        return $this->views->render('auth/login', [
            'title' => 'Accedi',
            'description' => 'Accedi al centro operativo HAPA.',
            'environment' => $this->environment,
            'correlationId' => $request->attributes->getString('correlation_id'),
            'csrfToken' => $session instanceof WebSession ? $session->csrfToken('login') : '',
            'next' => $next,
            'email' => $request->attributes->getString('login_email'),
            'error' => $request->attributes->getString('login_error'),
        ], 'layouts/auth', $request->attributes->getInt('login_status', Response::HTTP_OK));
    }

    public function recovery(Request $request): Response
    {
        return $this->views->render('auth/recovery', [
            'title' => 'Recupera accesso',
            'description' => 'Avvia il recupero sicuro delle credenziali.',
            'environment' => $this->environment,
            'correlationId' => $request->attributes->getString('correlation_id'),
        ], 'layouts/auth');
    }

    public function dashboard(Request $request): Response
    {
        $runtime = $this->runtimeOverview?->snapshot() ?? [
            'business' => ['open_orders' => 0, 'customers' => 0, 'catalog_items' => 0, 'shipments_today' => 0],
            'inbox' => [],
            'outbox' => [],
            'lag_seconds' => ['inbox_failed_oldest' => 0, 'outbox_due_oldest' => 0],
            'audit_last_24h' => 0,
        ];

        return $this->operational($request, 'ui/dashboard', 'dashboard', [
            'title' => 'Centro operativo',
            'eyebrow' => 'Panoramica',
            'description' => 'Controlla anagrafiche, catalogo, ordini e stato delle integrazioni da un unico punto.',
            'metrics' => [
                ['label' => 'Ordini da lavorare', 'value' => (string) $runtime['business']['open_orders'], 'detail' => 'Ordini non ancora chiusi o annullati', 'tone' => 'neutral'],
                ['label' => 'Clienti censiti', 'value' => (string) $runtime['business']['customers'], 'detail' => 'Anagrafiche canoniche persistite', 'tone' => 'info'],
                ['label' => 'Prodotti sincronizzati', 'value' => (string) $runtime['business']['catalog_items'], 'detail' => 'Prodotti presenti nel catalogo HAPA', 'tone' => 'success'],
                ['label' => 'Spedizioni di oggi', 'value' => (string) $runtime['business']['shipments_today'], 'detail' => 'Spedizioni create dalla mezzanotte', 'tone' => 'neutral'],
            ],
            'runtime' => $runtime,
            'workstreams' => [
                ['label' => 'Marketplace', 'detail' => 'SellRapido, Amazon, eMAG, Temu e IBS', 'status' => 'Pianificato', 'tone' => 'neutral', 'icon' => 'integration'],
                ['label' => 'Anagrafiche', 'detail' => 'Clienti, identità esterne, indirizzi e ordini', 'status' => 'Ordini persistenti', 'tone' => 'success', 'icon' => 'customer'],
                ['label' => 'Catalogo', 'detail' => 'Anagrafica prodotti, prezzo e stock Space, ricarichi gestiti da interfaccia', 'status' => 'Modello pronto', 'tone' => 'success', 'icon' => 'box'],
                ['label' => 'Space', 'detail' => 'Sorgente di prezzo e stock e destinazione degli ordini', 'status' => 'Acquisti operativi', 'tone' => 'success', 'icon' => 'integration'],
                ['label' => 'Automazioni esterne', 'detail' => 'Runtime separato nel repository hapa-automation tramite RabbitMQ', 'status' => 'Estratto', 'tone' => 'info', 'icon' => 'automation'],
                ['label' => 'Corrieri', 'detail' => 'GLS e BRT (Bartolini)', 'status' => 'Contratti pronti', 'tone' => 'info', 'icon' => 'truck'],
            ],
        ]);
    }

    public function customers(Request $request): Response
    {
        $query = trim($request->query->getString('q'));
        $status = $request->query->getString('status');
        $session = $request->attributes->get('security_session');

        return $this->operational($request, 'ui/customers', 'customers', [
            'title' => 'Clienti',
            'eyebrow' => 'Anagrafiche',
            'description' => 'Gestisci il profilo cliente canonico, i contatti, gli indirizzi e le identità provenienti dai diversi canali.',
            'query' => $query,
            'selectedStatus' => in_array($status, ['active', 'inactive', 'archived'], true) ? $status : '',
            'customers' => $this->customerReadModel?->search($query, $status) ?? [],
            'canManageCustomers' => $this->can($request, 'customers.manage'),
            'createCustomerCsrfToken' => $session instanceof WebSession ? $session->csrfToken('customer.create') : '',
            'customerError' => $request->query->getString('error'),
        ]);
    }

    public function customerDetail(Request $request): Response
    {
        $customerId = $request->attributes->getString('customerId');
        $customer = $this->customerReadModel?->detail($customerId);
        $session = $request->attributes->get('security_session');

        return $this->operational($request, 'ui/customer-detail', 'customers', [
            'title' => $customer === null ? sprintf('Cliente %s', $customerId) : (string) $customer['display_name'],
            'eyebrow' => 'Scheda cliente',
            'description' => 'Profilo canonico, contatti, identità esterne, indirizzi e ordini collegati.',
            'customerId' => $customerId,
            'customer' => $customer,
            'canManageCustomers' => $this->can($request, 'customers.manage'),
            'updateCustomerCsrfToken' => $session instanceof WebSession ? $session->csrfToken('customer.update.' . $customerId) : '',
            'archiveCustomerCsrfToken' => $session instanceof WebSession ? $session->csrfToken('customer.archive.' . $customerId) : '',
            'customerSaved' => $request->query->getBoolean('saved'),
            'customerError' => $request->query->getString('error'),
        ]);
    }

    public function orders(Request $request): Response
    {
        $query = trim($request->query->getString('q'));
        $status = $request->query->getString('status');
        $status = in_array($status, ['to_process', 'waiting_goods', 'picking', 'manual_review', 'completed', 'cancelled'], true)
            ? $status
            : '';

        return $this->operational($request, 'ui/orders', 'orders', [
            'title' => 'Ordini',
            'eyebrow' => 'Anagrafiche e operatività',
            'description' => 'Consulta l’anagrafica ordini e controlla ogni origine lungo il flusso di fulfilment.',
            'query' => $query,
            'selectedStatus' => $status,
            'orders' => $this->orderReadModel?->search($query, $status) ?? [],
        ]);
    }

    public function catalog(Request $request): Response
    {
        $query = trim($request->query->getString('q'));
        $catalog = $this->catalogReadModel?->search($query) ?? [
            'items' => [],
            'metrics' => ['total' => 0, 'pending_review' => 0, 'active' => 0, 'stale' => 0],
        ];
        $session = $request->attributes->get('security_session');
        $catalogItems = $catalog['items'];
        foreach ($catalogItems as &$item) {
            $item['review_csrf_token'] = $session instanceof WebSession
                ? $session->csrfToken('catalog.review.' . (string) $item['id'])
                : '';
            $item['availability_csrf_token'] = $session instanceof WebSession
                ? $session->csrfToken('catalog.availability.' . (string) $item['id'])
                : '';
        }
        unset($item);
        $pricePreviews = $this->pricingPreview?->forProducts($catalogItems) ?? [];
        foreach ($catalogItems as &$item) {
            $item['price_previews'] = $pricePreviews[(int) $item['id']] ?? [];
        }
        unset($item);
        $pricingRules = $this->pricingRules?->all() ?? [];
        foreach ($pricingRules as &$rule) {
            $rule['update_csrf_token'] = $session instanceof WebSession
                ? $session->csrfToken('pricing.update.' . (string) $rule['id'])
                : '';
            $rule['retire_csrf_token'] = $session instanceof WebSession
                ? $session->csrfToken('pricing.retire.' . (string) $rule['id'])
                : '';
        }
        unset($rule);

        return $this->operational($request, 'ui/catalog', 'catalog', [
            'title' => 'Anagrafica prodotti, prezzi e stock',
            'eyebrow' => 'Catalogo commerciale',
            'description' => 'Consulta prezzo e stock sincronizzati da Space e gestisci da interfaccia le regole di ricarico applicate alle offerte.',
            'query' => $query,
            'catalogItems' => $catalogItems,
            'catalogMetrics' => $catalog['metrics'],
            'pricingRules' => $pricingRules,
            'marketplaces' => $this->pricingRules?->marketplaces() ?? [],
            'createPricingCsrfToken' => $session instanceof WebSession ? $session->csrfToken('pricing.create') : '',
            'pricingSaved' => $request->query->getBoolean('pricing_saved'),
            'pricingError' => $request->query->getString('pricing_error'),
            'reviewSaved' => $request->query->getBoolean('review_saved'),
            'reviewError' => $request->query->getString('review_error'),
            'availabilitySaved' => $request->query->getBoolean('availability_saved'),
            'availabilityError' => $request->query->getString('availability_error'),
        ]);
    }

    public function orderDetail(Request $request): Response
    {
        $orderId = $request->attributes->getString('orderId');
        $order = $this->orderReadModel?->detail($orderId);
        $session = $request->attributes->get('security_session');
        $user = $request->attributes->get('current_user');
        $canManagePurchase = $user instanceof UserIdentity
            && ($this->authorization?->allows($user, 'orders.manage') ?? false);

        return $this->operational($request, 'ui/order-detail', 'orders', [
            'title' => sprintf('Ordine %s', $order['order_number'] ?? $orderId),
            'eyebrow' => 'Dettaglio ordine',
            'description' => 'Vista completa di cliente, origine, righe, acquisti verso Space, spedizioni, snapshot degli indirizzi e cronologia.',
            'orderId' => $orderId,
            'order' => $order,
            'canManagePurchase' => $canManagePurchase,
            'spacePurchaseCsrfToken' => $canManagePurchase && $session instanceof WebSession
                ? $session->csrfToken('order.space-purchase.' . $orderId)
                : '',
            'purchaseGenerated' => $request->query->getBoolean('purchase_generated'),
            'purchaseError' => $request->query->getString('purchase_error'),
        ]);
    }

    public function picking(Request $request): Response
    {
        return $this->collection($request, 'picking', [
            'title' => 'Picking',
            'eyebrow' => 'Magazzino',
            'description' => 'Gestisci sessioni, scansioni barcode, anomalie e decisioni sui parziali.',
            'searchLabel' => 'Cerca ordine, SKU, EAN o postazione',
            'filters' => ['Tutte le sessioni', 'Da iniziare', 'In corso', 'Con anomalie', 'Completate'],
            'columns' => ['Sessione', 'Ordine', 'Operatore', 'Avanzamento', 'Anomalie', 'Azioni'],
            'emptyTitle' => 'Nessuna sessione di picking',
            'emptyBody' => 'Le sessioni saranno create quando il modulo magazzino e le relative policy saranno disponibili.',
            'emptyIcon' => 'scan',
            'primaryAction' => 'Avvia picking',
        ]);
    }

    public function shipments(Request $request): Response
    {
        $query = trim($request->query->getString('q'));
        $status = $request->query->getString('status');
        $status = in_array($status, ['pending', 'created', 'label_available', 'shipped', 'error', 'cancelled'], true)
            ? $status
            : '';

        return $this->operational($request, 'ui/shipments', 'shipments', [
            'title' => 'Spedizioni',
            'eyebrow' => 'Logistica',
            'description' => 'Controlla corriere, colli, pesi, etichette, tracking e riconciliazioni per GLS e BRT (Bartolini).',
            'query' => $query,
            'selectedStatus' => $status,
            'shipments' => $this->shipmentReadModel?->search($query, $status) ?? [],
        ]);
    }

    public function integrations(Request $request): Response
    {
        $session = $request->attributes->get('security_session');
        $accounts = $this->integrationAccounts?->all() ?? [];
        foreach ($accounts as &$account) {
            $account = $this->decorateIntegrationAccount($account, $session);
            $account['secret_fields'] = $this->providerSecretFields?->forProvider((string) $account['provider_code']) ?? [];
        }
        unset($account);
        $availableCapabilities = $this->integrationConfiguration?->availableCapabilities() ?? [];
        $accountCounts = [];
        foreach ($accounts as $account) {
            $provider = (string) $account['provider_code'];
            $accountCounts[$provider] = ($accountCounts[$provider] ?? 0) + 1;
        }

        return $this->operational($request, 'ui/integrations', 'integrations', [
            'title' => 'Integrazioni',
            'eyebrow' => 'Ecosistema',
            'description' => 'Configura account e provider. L’esecuzione asincrona risiede nel servizio separato hapa-automation.',
            'integrations' => [
                ['name' => 'hapa-automation', 'kind' => 'Servizio separato · RabbitMQ · database proprio', 'code' => 'automation', 'status' => 'Operativo', 'tone' => 'success'],
                ['name' => 'SellRapido', 'kind' => 'Connettore aggregatore · import ordini IBS', 'code' => 'sellrapido', 'status' => 'Operativo', 'tone' => 'success'],
                ['name' => 'Amazon', 'kind' => self::MARKETPLACE_CHANNEL_KIND, 'code' => 'amazon', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'eMAG', 'kind' => self::MARKETPLACE_CHANNEL_KIND, 'code' => 'emag', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'Temu', 'kind' => self::MARKETPLACE_CHANNEL_KIND, 'code' => 'temu', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'IBS', 'kind' => 'Canale · ordini tramite SellRapido', 'code' => 'ibs', 'status' => 'Disponibile via SellRapido', 'tone' => 'success'],
                ['name' => 'Space', 'kind' => 'Sorgente prezzo e stock · destinazione ordini', 'code' => 'space', 'status' => 'Acquisti operativi', 'tone' => 'success'],
                ['name' => 'GLS', 'kind' => 'Corriere · integrazione dedicata', 'code' => 'gls', 'status' => 'Contratto pronto', 'tone' => 'info'],
                ['name' => 'BRT (Bartolini)', 'kind' => 'Corriere · integrazione dedicata', 'code' => 'brt', 'status' => 'Contratto pronto', 'tone' => 'info'],
            ],
            'configuredAccounts' => $accounts,
            'availableCapabilities' => $availableCapabilities,
            'accountCounts' => $accountCounts,
            'createIntegrationCsrfToken' => $session instanceof WebSession ? $session->csrfToken('integration.create') : '',
            'saved' => $request->query->getBoolean('saved'),
            'secretsSaved' => $request->query->getBoolean('secrets_saved'),
            'secretsRevoked' => $request->query->getBoolean('secrets_revoked'),
            'configurationSynced' => $request->query->getBoolean('configuration_synced'),
            'statusRefreshed' => $request->query->getBoolean('status_refreshed'),
            'connectionTested' => $request->query->getBoolean('connection_tested'),
            'ordersImported' => $request->query->getBoolean('orders_imported'),
            'ordersPublished' => max(0, $request->query->getInt('published')),
            'configurationError' => $request->query->getString('error'),
        ]);
    }

    public function users(Request $request): Response
    {
        return $this->collection($request, 'users', [
            'title' => 'Utenti e ruoli',
            'eyebrow' => 'Amministrazione',
            'description' => 'Gestisci accessi, ruoli, sessioni e requisiti di sicurezza.',
            'searchLabel' => 'Cerca per nome, email o ruolo',
            'filters' => ['Tutti gli utenti', 'Amministratori', 'Operatori', 'Sola lettura', 'Sospesi'],
            'columns' => ['Utente', 'Ruolo', 'MFA', 'Ultimo accesso', 'Stato', 'Azioni'],
            'emptyTitle' => 'Gestione utenti non ancora attiva',
            'emptyBody' => 'La UI è pronta per il futuro repository utenti, le sessioni sicure e l’autorizzazione deny-by-default.',
            'emptyIcon' => 'users',
            'primaryAction' => 'Invita utente',
        ]);
    }

    /**
     * @param array<string,mixed> $account
     * @return array<string,mixed>
     */
    private function decorateIntegrationAccount(array $account, mixed $session): array
    {
        $id = (string) $account['id'];
        $actions = [
            'update_csrf_token' => 'integration.update.',
            'retire_csrf_token' => 'integration.retire.',
            'replace_secrets_csrf_token' => 'integration.secrets.replace.',
            'revoke_secrets_csrf_token' => 'integration.secrets.revoke.',
            'sync_configuration_csrf_token' => 'integration.configuration.sync.',
            'refresh_status_csrf_token' => 'integration.status.refresh.',
            'change_status_csrf_token' => 'integration.status.change.',
            'connection_test_csrf_token' => 'integration.connection-test.',
            'orders_import_csrf_token' => 'integration.orders.import.',
            'catalog_sync_csrf_token' => 'integration.catalog.sync.',
        ];
        foreach ($actions as $field => $action) {
            $account[$field] = $session instanceof WebSession ? $session->csrfToken($action . $id) : '';
        }

        return $account;
    }

    public function audit(Request $request): Response
    {
        $query = trim($request->query->getString('q'));
        $entityType = $request->query->getString('entity_type');

        return $this->operational($request, 'ui/audit', 'audit', [
            'title' => 'Audit',
            'eyebrow' => 'Controllo',
            'description' => 'Ricostruisci azioni operative, cambi di stato e accessi sensibili.',
            'query' => $query,
            'selectedEntityType' => $entityType,
            'entityTypes' => $this->auditReadModel?->entityTypes() ?? [],
            'auditEntries' => $this->auditReadModel?->search($query, $entityType) ?? [],
        ]);
    }

    public function settings(Request $request): Response
    {
        return $this->operational($request, 'ui/settings', 'settings', [
            'title' => 'Impostazioni',
            'eyebrow' => 'Configurazione',
            'description' => 'Consulta le preferenze operative. I valori infrastrutturali restano gestiti tramite ambiente e secret.',
            'groups' => [
                [
                    'title' => 'Applicazione',
                    'description' => 'Preferenze di visualizzazione e comportamento locale.',
                    'fields' => [
                        ['label' => 'Lingua', 'value' => 'Italiano'],
                        ['label' => 'Fuso orario', 'value' => 'Europe/Rome'],
                        ['label' => 'Formato data', 'value' => 'GG/MM/AAAA'],
                    ],
                ],
                [
                    'title' => 'Notifiche operative',
                    'description' => 'Metriche, code e dead letter sono esposte dal servizio hapa-automation.',
                    'fields' => [
                        ['label' => 'Canale eventi', 'value' => 'RabbitMQ'],
                        ['label' => 'Runtime automazioni', 'value' => 'Servizio esterno'],
                        ['label' => 'Ordini in revisione', 'value' => 'Non configurato'],
                    ],
                ],
            ],
        ]);
    }

    public function profile(Request $request): Response
    {
        return $this->operational($request, 'ui/settings', 'profile', [
            'title' => 'Profilo',
            'eyebrow' => 'Account',
            'description' => 'Gestisci identità, password, MFA e sessioni attive.',
            'groups' => [
                [
                    'title' => 'Identità',
                    'description' => 'I dati saranno disponibili dopo l’attivazione del contesto di autenticazione.',
                    'fields' => [
                        ['label' => 'Nome', 'value' => self::UNAVAILABLE],
                        ['label' => 'Email', 'value' => self::UNAVAILABLE],
                        ['label' => 'Ruolo', 'value' => self::UNAVAILABLE],
                    ],
                ],
                [
                    'title' => 'Sicurezza',
                    'description' => 'Password, MFA e revoca delle sessioni richiederanno una sessione autenticata.',
                    'fields' => [
                        ['label' => 'Password', 'value' => 'Gestione non attiva'],
                        ['label' => 'MFA', 'value' => 'Gestione non attiva'],
                        ['label' => 'Sessioni', 'value' => 'Nessun dato esposto'],
                    ],
                ],
            ],
        ]);
    }

    public function notFound(Request $request): Response
    {
        return $this->views->render('ui/error', [
            'title' => 'Pagina non trovata',
            'eyebrow' => 'Errore 404',
            'description' => 'La pagina richiesta non appartiene all’interfaccia operativa HAPA.',
            'active' => '',
            'navigation' => $this->navigation(),
            'environment' => $this->environment,
            'correlationId' => $request->attributes->getString('correlation_id'),
        ], status: Response::HTTP_NOT_FOUND);
    }

}
