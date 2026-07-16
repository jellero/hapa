# HAPA

HAPA è la piattaforma proprietaria che governa anagrafiche clienti e ordini, ciclo marketplace, Space API, magazzino e corrieri GLS e BRT (Bartolini), predisponendo l’origine ordini del futuro e-commerce B2C.

Il progetto usa un **framework custom proprietario in PHP 8.4**, costruito su componenti Symfony selezionati e su confini applicativi espliciti. PostgreSQL conserva lo stato autorevole del processo; Redis supporta coordinamento e capacità temporanee; le integrazioni esterne attraversano adapter tipizzati, transactional outbox, retry e riconciliazione.

## Obiettivo operativo

Il flusso completo previsto comprende:

1. acquisizione o aggiornamento dell’anagrafica cliente e delle identità esterne;
2. importazione incrementale dell’ordine dal marketplace;
3. accettazione sul canale sorgente;
4. acquisizione e normalizzazione degli indirizzi;
5. persistenza idempotente di ordine, cliente e righe;
6. invio dell’ordine a Space tramite API;
7. aggiornamento della disponibilità;
8. picking barcode completo o parziale;
9. definizione di colli, peso reale, volumetrico e tariffabile;
10. creazione spedizione ed etichetta tramite il corriere selezionato;
11. restituzione di tracking e fulfilment al marketplace;
12. controllo operativo tramite audit, retry e riconciliazione.

## Stato del progetto

### Implementato e verificato

- bootstrap condiviso HTTP e CLI;
- composition root separato con container Symfony compilato, servizi privati, alias e handler taggati;
- configurazioni tipizzate e `Clock` iniettato;
- configurazione ambiente validata e secret file centralizzati;
- trusted proxy espliciti;
- Kernel HTTP con correlation ID, error handling e header di sicurezza;
- logging JSON con redazione dei dati sensibili;
- policy HTTP condivisa anche per gli errori di bootstrap e richieste malformate gestite come `400`;
- health check liveness/readiness con PostgreSQL, Redis e verifica schema;
- PostgreSQL con migrazioni, `JSONB`, `TIMESTAMPTZ` e vincoli principali;
- Docker development e production;
- runtime applicativo separato dall’immagine migrazioni;
- CI con audit Composer, PostgreSQL, Redis, PHPUnit, PHPStan e smoke test production;
- contratto Shipping provider-neutral e contratti iniziali tipizzati per Marketplace, Space, GLS e BRT;
- distinzione tipizzata tra canale marketplace e connettore tecnico;
- invarianti runtime sui contratti Marketplace, Space e Shipping;
- dipendenze tra moduli dichiarate e verificate automaticamente, senza cicli;
- canali futuri registrati per Amazon, eMAG, Temu e IBS, con SellRapido come connettore aggregatore;
- anagrafica clienti con stato, tipo, contatti, dati fiscali, identità esterne e indirizzi predefiniti;
- anagrafica ordini con numero interno, cliente, origine vincolata e snapshot distinti di spedizione e fatturazione;
- aggregato `Order` con righe immutabili, invarianti sulle quantità, macchina a stati deterministica ed eventi di dominio;
- storico versionato delle transizioni ordine e numero riga stabile, protetti da vincoli PostgreSQL;
- origine `b2c_ecommerce` predisposta nel modello, con e-commerce completo mantenuto in roadmap;
- interfaccia operativa server-rendered, responsive e accessibile per tutte le aree previste;
- schermate di accesso, dashboard, clienti, ordini, picking, spedizioni, automazioni, integrazioni, audit, utenti e impostazioni;
- repository PostgreSQL dell’aggregato ordine con mapping completo e optimistic locking atomico;
- transaction manager e scrittura di ordine, transizioni e outbox nello stesso commit;
- transactional outbox operativa con schema versione, correlation ID e deduplica;
- worker concorrente con `FOR UPDATE SKIP LOCKED`, lock recovery, retry con backoff e jitter e dead letter;
- scheduler persistente con sette job ordini/spedizioni a intervallo di dieci minuti, censiti ma disattivati fino agli adapter reali;
- handler interno idempotente che proietta gli eventi ordine nell’audit log;
- comando CLI one-shot `automation:run`, adatto a cron o orchestratore;
- manifest versionato per la readiness dello schema PostgreSQL;
- documentazione architetturale, sicurezza e roadmap.

### Prossima sequenza

La roadmap prosegue dalla base transazionale ora operativa verso le vertical slice reali:

1. aggregato e repository cliente, query paginata per clienti e ordini;
2. autenticazione, autorizzazione, CSRF e audit delle azioni UI;
3. discovery e adapter del primo canale SellRapido/marketplace;
4. vertical slice Marketplace → HAPA → Space;
5. picking, conferma manuale dei parziali e adapter GLS/BRT;
6. metriche, gestione operativa delle dead letter e supervisione continuativa dei worker.

Gli adapter provider restano disattivati finché contratti, credenziali e sandbox non sono verificati. Il runtime delle automazioni non simula chiamate esterne. Il piano è documentato in [`docs/AUTOMATIONS.md`](docs/AUTOMATIONS.md), le anagrafiche in [`docs/CUSTOMERS_AND_ORDERS.md`](docs/CUSTOMERS_AND_ORDERS.md), marketplace e corrieri rispettivamente in [`docs/MARKETPLACES.md`](docs/MARKETPLACES.md) e [`docs/CARRIERS.md`](docs/CARRIERS.md).

## Architettura

```text
app/
  Composition/             composition root e grafo servizi
  Core/                    runtime e servizi trasversali
  Modules/                 dominio, casi d’uso, contratti e adapter
bin/
  console                  ingresso CLI
config/
  module-dependencies.php  dipendenze ammesse tra moduli
  routes.php               composizione HTTP e routing
database/
  migrations/              schema PostgreSQL versionato
docs/
  ARCHITECTURE.md           riferimento architetturale
  CARRIERS.md               contratto Shipping, GLS e BRT
  DEVELOPMENT_WORKFLOW.md   percorso canonico di sviluppo
  SYMFONY_ALIGNMENT.md      adozione selettiva delle primitive Symfony
  SECURITY.md               requisiti e policy di sicurezza
  TODO.md                   roadmap e gate di completamento
docker/
  php/                      immagini e configurazioni PHP
  nginx.conf                frontiera HTTP applicativa
public/
  assets/                   design system, interazioni e icone UI
  index.php                 front controller HTTP
scripts/
  check-architecture.php    verifica automatica dei confini
templates/
  auth/                     accesso e recupero credenziali
  layouts/                  shell applicativa e autenticazione
  ui/                       schermate operative
tests/
  Unit/
  Integration/
  Architecture/
```

Principi principali:

- moduli organizzati per capacità di business;
- dominio indipendente dai dettagli dei provider;
- constructor injection e composition root esplicito;
- PostgreSQL come sorgente autorevole;
- consistenza transazionale interna e consistenza eventuale ai confini;
- idempotenza end-to-end;
- transactional outbox per gli effetti esterni;
- osservabilità e audit incorporati nei flussi;
- sviluppo per vertical slice complete.

Il documento completo è [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). Il confronto con le pratiche Symfony è in [`docs/SYMFONY_ALIGNMENT.md`](docs/SYMFONY_ALIGNMENT.md).

## Requisiti locali

- Docker con Docker Compose v2;
- Git;
- porte locali previste dal Compose disponibili;
- PHP e Composer locali opzionali, utili per eseguire i controlli fuori dai container.

## Avvio locale

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec php composer install
docker compose exec php vendor/bin/phinx migrate -e development
```

Verifica dello stack:

```bash
docker compose ps
docker compose exec php php bin/console system:check
docker compose exec php php bin/console automation:run --worker=hapa-local-1
```

Endpoint tecnici:

```text
GET http://localhost:8080/
GET http://localhost:8080/health/live
GET http://localhost:8080/health/ready
```

`/health/live` verifica il processo applicativo. `/health/ready` verifica dipendenze e versione minima dello schema.

Interfaccia operativa:

```text
GET http://localhost:8080/login
GET http://localhost:8080/ui
```

La UI espone il piano delle sette automazioni e lo stato del runtime, ma non consente ancora azioni mutative né attiva provider reali. Autenticazione, autorizzazione e collegamento dei read model restano gate obbligatori prima dell’esercizio operativo.

`automation:run` è one-shot: recupera lock scaduti, pianifica i job abilitati ed elabora un batch outbox. In produzione va richiamato da un cron o orchestratore con identità worker stabile. I sette job di integrazione sono creati disabilitati e devono essere attivati soltanto quando il relativo handler provider ha superato i test sandbox.

## Comandi di qualità

```bash
composer ci:fast
composer ci:full
```

`ci:fast` esegue controllo architetturale, test e PHPStan. `ci:full` aggiunge validazione Composer e audit delle dipendenze.

La pipeline GitHub esegue inoltre:

- migrazioni su PostgreSQL reale;
- integration test PostgreSQL e Redis;
- build delle immagini production;
- avvio dello stack production;
- applicazione delle migrazioni tramite immagine dedicata;
- smoke test HTTP di liveness, route UI, asset e header di sicurezza.

## Produzione

La topologia production prevede reverse proxy esterno per TLS e HSTS, Nginx applicativo pubblicato su loopback, rete interna per PostgreSQL e Redis, filesystem applicativo read-only, processi con privilegi ridotti e secret montati tramite file.

Preparazione minima:

```bash
cp .env.production.example .env.production
mkdir -p secrets
umask 077
openssl rand -base64 48 > secrets/db_password.txt
openssl rand -base64 48 > secrets/redis_password.txt
```

Impostare `IMAGE_TAG` con il commit distribuito e configurare `TRUSTED_PROXIES` con gli indirizzi effettivi della frontiera.

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml config
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d postgres redis
docker compose --env-file .env.production -f docker-compose.prod.yml \
  --profile tools run --rm migration
docker compose --env-file .env.production -f docker-compose.prod.yml up -d php nginx
```

I requisiti completi sono definiti in [`docs/SECURITY.md`](docs/SECURITY.md).

## Documentazione

L’indice documentale è [`docs/README.md`](docs/README.md).

| Documento | Contenuto |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | confini, dominio, persistenza, flussi, runtime, deploy e operatività |
| [`docs/AUTOMATIONS.md`](docs/AUTOMATIONS.md) | scheduler, outbox, sette job ordini/spedizioni, retry e criteri di attivazione |
| [`docs/CARRIERS.md`](docs/CARRIERS.md) | ownership Shipping, GLS e BRT, discovery, failure mode e gate operativi |
| [`docs/CUSTOMERS_AND_ORDERS.md`](docs/CUSTOMERS_AND_ORDERS.md) | modello canonico di clienti, identità, indirizzi, ordini e confine B2C |
| [`docs/DEVELOPMENT_WORKFLOW.md`](docs/DEVELOPMENT_WORKFLOW.md) | ownership, livelli applicativi, dipendenze e Definition of Done |
| [`docs/INTERFACE.md`](docs/INTERFACE.md) | architettura UI, mappa delle schermate, accessibilità e sicurezza |
| [`docs/MARKETPLACES.md`](docs/MARKETPLACES.md) | canali futuri, connettori, gate di discovery e prevenzione dei duplicati |
| [`docs/SYMFONY_ALIGNMENT.md`](docs/SYMFONY_ALIGNMENT.md) | componenti Symfony adottati, esclusi o valutati |
| [`docs/SECURITY.md`](docs/SECURITY.md) | autenticazione, sessione, provider, worker, dati e produzione |
| [`docs/TODO.md`](docs/TODO.md) | sequenza esecutiva, gate e criterio end-to-end |
| [`docs/PR_CHECKLIST.md`](docs/PR_CHECKLIST.md) | controlli di perimetro, sicurezza, test, CI e rilascio |

Ogni modifica a una decisione architetturale, a un requisito di sicurezza o alla sequenza di sviluppo aggiorna il documento corrispondente nello stesso changeset.

## Licenza e proprietà

Il codice e la documentazione sono proprietari. Utilizzo, distribuzione e modifica seguono gli accordi definiti dal proprietario del repository.
