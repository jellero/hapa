<?php

declare(strict_types=1);

namespace Hapa\Core\Ui;

use Hapa\Core\View\ViewRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class UiController
{
    public function __construct(
        private ViewRenderer $views,
        private string $environment,
    ) {
    }

    public function login(Request $request): Response
    {
        return $this->views->render('auth/login', [
            'title' => 'Accedi',
            'description' => 'Accedi al centro operativo HAPA.',
            'environment' => $this->environment,
            'correlationId' => $request->attributes->getString('correlation_id'),
        ], 'layouts/auth');
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
        return $this->operational($request, 'ui/dashboard', 'dashboard', [
            'title' => 'Centro operativo',
            'eyebrow' => 'Panoramica',
            'description' => 'Controlla il ciclo ordine, le eccezioni e lo stato delle integrazioni da un unico punto.',
            'metrics' => [
                ['label' => 'Ordini da lavorare', 'value' => '—', 'detail' => 'Repository ordini non collegato', 'tone' => 'neutral'],
                ['label' => 'Clienti censiti', 'value' => '—', 'detail' => 'Repository clienti non collegato', 'tone' => 'info'],
                ['label' => 'Revisione manuale', 'value' => '—', 'detail' => 'Disponibile con il dominio Order', 'tone' => 'warning'],
                ['label' => 'Spedizioni di oggi', 'value' => '—', 'detail' => 'Adapter corriere non collegati', 'tone' => 'success'],
            ],
            'workstreams' => [
                ['label' => 'Marketplace', 'detail' => 'SellRapido, Amazon, eMAG, Temu e IBS', 'status' => 'Pianificato', 'tone' => 'neutral', 'icon' => 'integration'],
                ['label' => 'Anagrafiche', 'detail' => 'Clienti, identità esterne, indirizzi e ordini', 'status' => 'Schema pronto', 'tone' => 'info', 'icon' => 'customer'],
                ['label' => 'Space', 'detail' => 'Invio ordine e disponibilità', 'status' => 'Contratto pronto', 'tone' => 'info', 'icon' => 'automation'],
                ['label' => 'Magazzino', 'detail' => 'Picking barcode e parziali', 'status' => 'Pianificato', 'tone' => 'neutral', 'icon' => 'scan'],
                ['label' => 'Corrieri', 'detail' => 'GLS e BRT (Bartolini)', 'status' => 'Contratti pronti', 'tone' => 'info', 'icon' => 'truck'],
            ],
        ]);
    }

    public function customers(Request $request): Response
    {
        return $this->collection($request, 'customers', [
            'title' => 'Clienti',
            'eyebrow' => 'Anagrafiche',
            'description' => 'Gestisci il profilo cliente canonico, i contatti, gli indirizzi e le identità provenienti dai diversi canali.',
            'searchLabel' => 'Cerca per codice, nome, email o identità esterna',
            'filters' => ['Tutti i clienti', 'Attivi', 'Inattivi', 'Archiviati'],
            'columns' => ['Codice', 'Cliente', 'Contatti', 'Origini', 'Ordini', 'Stato', 'Azioni'],
            'emptyTitle' => 'Nessun cliente disponibile',
            'emptyBody' => 'Schema e modello di dominio sono pronti; i clienti compariranno dopo il collegamento del repository e dei casi d’uso protetti.',
            'emptyIcon' => 'customer',
            'primaryAction' => 'Nuovo cliente',
        ]);
    }

    public function customerDetail(Request $request): Response
    {
        $customerId = $request->attributes->getString('customerId');

        return $this->operational($request, 'ui/customer-detail', 'customers', [
            'title' => sprintf('Cliente %s', $customerId),
            'eyebrow' => 'Scheda cliente',
            'description' => 'Profilo canonico, contatti, identità esterne, indirizzi e ordini collegati.',
            'customerId' => $customerId,
        ]);
    }

    public function orders(Request $request): Response
    {
        return $this->collection($request, 'orders', [
            'title' => 'Ordini',
            'eyebrow' => 'Anagrafiche e operatività',
            'description' => 'Consulta l’anagrafica ordini e controlla ogni origine lungo l’intero flusso di fulfilment.',
            'searchLabel' => 'Cerca per ordine, cliente, origine, SKU o tracking',
            'filters' => ['Tutti gli stati', 'Da accettare', 'In attesa merce', 'Picking', 'Revisione manuale', 'Completati'],
            'columns' => ['Ordine', 'Cliente', 'Origine', 'Stato', 'Righe', 'Aggiornato', 'Azioni'],
            'emptyTitle' => 'Nessun ordine disponibile',
            'emptyBody' => 'Lo schema supporta ordini marketplace e la futura origine B2C; i dati compariranno dopo il collegamento del repository PostgreSQL.',
            'emptyIcon' => 'orders',
            'primaryAction' => 'Importa ordini',
        ]);
    }

    public function orderDetail(Request $request): Response
    {
        $orderId = $request->attributes->getString('orderId');

        return $this->operational($request, 'ui/order-detail', 'orders', [
            'title' => sprintf('Ordine %s', $orderId),
            'eyebrow' => 'Dettaglio ordine',
            'description' => 'Vista completa di cliente, origine, righe, snapshot degli indirizzi, delivery esterne e audit.',
            'orderId' => $orderId,
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
        return $this->collection($request, 'shipments', [
            'title' => 'Spedizioni',
            'eyebrow' => 'Logistica',
            'description' => 'Controlla corriere, colli, pesi, etichette, tracking e riconciliazioni per GLS e BRT (Bartolini).',
            'searchLabel' => 'Cerca ordine, spedizione o tracking',
            'filters' => ['Tutte le spedizioni', 'Da creare', 'Etichetta pronta', 'Spedite', 'Con errore', 'Annullate'],
            'columns' => ['Spedizione', 'Ordine', 'Corriere', 'Colli', 'Peso', 'Stato', 'Azioni'],
            'emptyTitle' => 'Nessuna spedizione disponibile',
            'emptyBody' => 'Le spedizioni compariranno dopo il completamento del dominio colli e degli adapter GLS e BRT.',
            'emptyIcon' => 'truck',
            'primaryAction' => 'Crea spedizione',
        ]);
    }

    public function automation(Request $request): Response
    {
        return $this->collection($request, 'automation', [
            'title' => 'Automazioni',
            'eyebrow' => 'Affidabilità',
            'description' => 'Ispeziona outbox, retry, dead letter, scheduler e riconciliazioni.',
            'searchLabel' => 'Cerca evento, ordine, provider o correlation ID',
            'filters' => ['Tutti i messaggi', 'Pending', 'Processing', 'Retry', 'Dead letter', 'Completati'],
            'columns' => ['Evento', 'Aggregato', 'Provider', 'Tentativi', 'Disponibile da', 'Azioni'],
            'emptyTitle' => 'Nessun messaggio operativo',
            'emptyBody' => 'Lo schema outbox è pronto; i messaggi appariranno dopo l’implementazione del repository e del worker.',
            'emptyIcon' => 'automation',
            'primaryAction' => 'Riconcilia',
        ]);
    }

    public function integrations(Request $request): Response
    {
        return $this->operational($request, 'ui/integrations', 'integrations', [
            'title' => 'Integrazioni',
            'eyebrow' => 'Ecosistema',
            'description' => 'Configura account marketplace, servizi e corrieri mantenendo separati canale, connettore e provider.',
            'integrations' => [
                ['name' => 'SellRapido', 'kind' => 'Connettore aggregatore', 'code' => 'sellrapido', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'Amazon', 'kind' => 'Canale · SP-API', 'code' => 'amazon', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'eMAG', 'kind' => 'Canale · Marketplace API', 'code' => 'emag', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'Temu', 'kind' => 'Canale · Partner Platform', 'code' => 'temu', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'IBS', 'kind' => 'Canale · via SellRapido', 'code' => 'ibs', 'status' => 'Pianificato', 'tone' => 'neutral'],
                ['name' => 'Space', 'kind' => 'Approvvigionamento', 'code' => 'space', 'status' => 'Contratto pronto', 'tone' => 'info'],
                ['name' => 'GLS', 'kind' => 'Corriere · integrazione dedicata', 'code' => 'gls', 'status' => 'Contratto pronto', 'tone' => 'info'],
                ['name' => 'BRT (Bartolini)', 'kind' => 'Corriere · integrazione dedicata', 'code' => 'brt', 'status' => 'Contratto pronto', 'tone' => 'info'],
            ],
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

    public function audit(Request $request): Response
    {
        return $this->collection($request, 'audit', [
            'title' => 'Audit',
            'eyebrow' => 'Controllo',
            'description' => 'Ricostruisci azioni operative, cambi di stato e accessi sensibili.',
            'searchLabel' => 'Cerca attore, azione, entità o correlation ID',
            'filters' => ['Tutti gli eventi', 'Clienti', 'Ordini', 'Spedizioni', 'Utenti', 'Retry e replay', 'Sicurezza'],
            'columns' => ['Data e ora', 'Attore', 'Azione', 'Entità', 'Correlation ID', 'Dettaglio'],
            'emptyTitle' => 'Nessun evento di audit',
            'emptyBody' => 'Gli eventi verranno esposti quando i casi d’uso applicativi scriveranno nell’audit log.',
            'emptyIcon' => 'audit',
            'primaryAction' => 'Esporta',
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
                    'description' => 'Canali e soglie saranno disponibili insieme a metriche e alerting.',
                    'fields' => [
                        ['label' => 'Dead letter', 'value' => 'Non configurato'],
                        ['label' => 'Provider indisponibile', 'value' => 'Non configurato'],
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
                        ['label' => 'Nome', 'value' => 'Non disponibile'],
                        ['label' => 'Email', 'value' => 'Non disponibile'],
                        ['label' => 'Ruolo', 'value' => 'Non disponibile'],
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

    /** @param array<string, mixed> $page */
    private function collection(Request $request, string $active, array $page): Response
    {
        $page['query'] = trim($request->query->getString('q'));
        $selectedFilter = $request->query->getString('status');
        /** @var list<string> $filters */
        $filters = $page['filters'];
        $page['selectedFilter'] = in_array($selectedFilter, $filters, true) ? $selectedFilter : '';
        $page['clearUrl'] = $request->getPathInfo();

        return $this->operational($request, 'ui/collection', $active, $page);
    }

    /** @param array<string, mixed> $page */
    private function operational(Request $request, string $template, string $active, array $page): Response
    {
        return $this->views->render($template, array_replace($page, [
            'active' => $active,
            'navigation' => $this->navigation(),
            'environment' => $this->environment,
            'correlationId' => $request->attributes->getString('correlation_id'),
        ]));
    }

    /** @return list<array{label: string, items: list<array{label: string, href: string, icon: string, active: string}>}> */
    private function navigation(): array
    {
        return [
            [
                'label' => 'Operatività',
                'items' => [
                    ['label' => 'Dashboard', 'href' => '/ui', 'icon' => 'dashboard', 'active' => 'dashboard'],
                    ['label' => 'Clienti', 'href' => '/ui/customers', 'icon' => 'customer', 'active' => 'customers'],
                    ['label' => 'Ordini', 'href' => '/ui/orders', 'icon' => 'orders', 'active' => 'orders'],
                    ['label' => 'Picking', 'href' => '/ui/picking', 'icon' => 'scan', 'active' => 'picking'],
                    ['label' => 'Spedizioni', 'href' => '/ui/shipments', 'icon' => 'truck', 'active' => 'shipments'],
                ],
            ],
            [
                'label' => 'Controllo',
                'items' => [
                    ['label' => 'Automazioni', 'href' => '/ui/automation', 'icon' => 'automation', 'active' => 'automation'],
                    ['label' => 'Integrazioni', 'href' => '/ui/integrations', 'icon' => 'integration', 'active' => 'integrations'],
                    ['label' => 'Audit', 'href' => '/ui/audit', 'icon' => 'audit', 'active' => 'audit'],
                ],
            ],
            [
                'label' => 'Amministrazione',
                'items' => [
                    ['label' => 'Utenti e ruoli', 'href' => '/ui/users', 'icon' => 'users', 'active' => 'users'],
                    ['label' => 'Impostazioni', 'href' => '/ui/settings', 'icon' => 'settings', 'active' => 'settings'],
                ],
            ],
        ];
    }
}
