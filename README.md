# HAPA

HAPA è l’applicazione proprietaria che governa le anagrafiche clienti, ordini e prodotti, il catalogo commerciale, il magazzino, le spedizioni e la configurazione delle integrazioni.

L’anagrafica prodotti conserva per ogni SKU il prezzo base e lo stock ricevuti da Space. Gli operatori gestiscono dall’interfaccia HAPA le regole di ricarico e controllano il prezzo finale destinato ai marketplace. Space resta la sorgente del dato di prezzo e disponibilità; HAPA resta la sorgente delle regole commerciali e dello stato applicativo.

Le automazioni non vengono sviluppate o eseguite in questo repository. Il servizio autonomo è [`jellero/hapa-automation`](https://github.com/jellero/hapa-automation), che contiene codice, configurazione, documentazione operativa, Compose, database e pipeline propri.

## Responsabilità di HAPA

HAPA possiede:

- anagrafiche clienti, identità esterne e indirizzi;
- anagrafica ordini e relativo dominio transazionale;
- anagrafica prodotti;
- prezzo e stock sincronizzati da Space;
- regole di ricarico gestite tramite interfaccia;
- prezzi finali e stato delle offerte marketplace;
- picking, colli, spedizioni e tracking;
- autenticazione, autorizzazione, audit e configurazione;
- outbox transazionale degli eventi prodotti dai casi d’uso.

HAPA non possiede:

- scheduler dei job;
- worker di automazione;
- retry e dead letter dei provider;
- cursori di polling dei provider;
- database operativo delle automazioni;
- adapter eseguiti in background.

Queste responsabilità appartengono esclusivamente al repository `hapa-automation`.

## Flussi principali

### Catalogo

1. `hapa-automation` acquisisce da Space le variazioni di prodotto, prezzo e stock.
2. Il risultato viene pubblicato su RabbitMQ con un evento versionato.
3. HAPA applica il dato ricevuto alla propria anagrafica prodotti.
4. Un operatore configura o modifica le regole di ricarico dall’interfaccia HAPA.
5. HAPA produce l’intenzione di pubblicare una nuova offerta.
6. `hapa-automation` pubblica prezzo e quantità sul marketplace e restituisce l’esito.

### Ordini e spedizioni

1. `hapa-automation` importa l’ordine dal marketplace.
2. HAPA persiste cliente, ordine, righe e snapshot degli indirizzi.
3. HAPA produce gli eventi applicativi nella transactional outbox.
4. `hapa-automation` esegue invio a Space, riconciliazioni e chiamate provider.
5. HAPA governa picking, decisioni manuali e dati di spedizione.
6. `hapa-automation` crea label e fulfilment tramite GLS, BRT e marketplace.

## Confine RabbitMQ

RabbitMQ trasporta eventi e comandi; non replica direttamente i database.

- PostgreSQL HAPA è autorevole per clienti, ordini, prodotti, ricarichi e stato commerciale.
- PostgreSQL `hapa-automation` è autorevole per scheduler, inbox, outbox, retry, dead letter, cursori e proiezioni operative.
- Ogni consumer è idempotente.
- I messaggi hanno `message_id`, `event_type`, `schema_version`, `occurred_at`, `correlation_id` e payload tipizzato.
- Nessun servizio accede direttamente al database dell’altro.

Il confine applicativo HAPA è descritto in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). L’architettura e la documentazione operativa delle automazioni sono mantenute nel repository [`hapa-automation`](https://github.com/jellero/hapa-automation/tree/agent/bootstrap-rabbitmq-runtime/docs).

## Stack

- PHP 8.4;
- componenti Symfony selezionati;
- PostgreSQL;
- Redis per capacità applicative temporanee;
- Phinx per le migrazioni;
- PHPUnit e PHPStan;
- Docker e Docker Compose.

## Architettura

```text
app/
  Composition/             composition root HAPA
  Core/                    runtime HTTP/CLI e servizi trasversali
  Modules/                 dominio, casi d’uso, contratti e adapter sincroni
bin/
  console                  comandi applicativi HAPA
config/
  module-dependencies.php  dipendenze ammesse tra moduli
  routes.php               routing HTTP
database/
  migrations/              schema PostgreSQL HAPA
docs/
  ARCHITECTURE.md           confini e flussi applicativi HAPA
  CATALOG_PRICING.md        anagrafica prodotti, prezzo, stock e ricarichi
```

Principi:

- PostgreSQL HAPA come sorgente autorevole del dominio HAPA;
- consistenza transazionale interna;
- consistenza eventuale tra servizi;
- idempotenza end-to-end;
- transactional outbox per gli eventi esterni;
- contratti di messaggio versionati;
- nessun database condiviso;
- sviluppo per vertical slice complete.

## Avvio locale

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec php composer install
docker compose exec php vendor/bin/phinx migrate -e development
docker compose exec php php bin/console system:check
```

Endpoint principali:

```text
GET http://localhost:8080/
GET http://localhost:8080/health/live
GET http://localhost:8080/health/ready
GET http://localhost:8080/login
GET http://localhost:8080/ui
GET http://localhost:8080/ui/catalog
GET http://localhost:8080/ui/integrations
```

Il runtime asincrono viene avviato esclusivamente dal repository `hapa-automation`, non dal Compose HAPA.

## Qualità

```bash
composer ci:fast
composer ci:full
```

`ci:fast` esegue controllo architetturale, test e PHPStan. `ci:full` aggiunge validazione Composer e audit delle dipendenze.

## Documentazione

| Documento | Contenuto |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | confini HAPA, messaggistica e proprietà dei dati |
| [`docs/CATALOG_PRICING.md`](docs/CATALOG_PRICING.md) | anagrafica prodotti, prezzo e stock Space, ricarichi UI |
| [`docs/CARRIERS.md`](docs/CARRIERS.md) | contratto Shipping, GLS e BRT |
| [`docs/CUSTOMERS_AND_ORDERS.md`](docs/CUSTOMERS_AND_ORDERS.md) | clienti, identità, indirizzi e ordini |
| [`docs/DEVELOPMENT_WORKFLOW.md`](docs/DEVELOPMENT_WORKFLOW.md) | percorso di sviluppo e ownership |
| [`docs/INTERFACE.md`](docs/INTERFACE.md) | interfaccia operativa HAPA |
| [`docs/MARKETPLACES.md`](docs/MARKETPLACES.md) | canali, connettori e account |
| [`docs/SECURITY.md`](docs/SECURITY.md) | requisiti di sicurezza |
| [`docs/TODO.md`](docs/TODO.md) | roadmap e gate HAPA |

La documentazione del runtime asincrono è mantenuta esclusivamente nel repository `hapa-automation`.

## Licenza e proprietà

Il codice e la documentazione sono proprietari. Utilizzo, distribuzione e modifica seguono gli accordi definiti dal proprietario del repository.
