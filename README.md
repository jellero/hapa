# HAPA

HAPA è la piattaforma proprietaria che governa il ciclo ordine tra marketplace, Space API, magazzino e GLS.

Il progetto usa un **framework custom proprietario in PHP 8.4**, costruito su componenti Symfony selezionati e su confini applicativi espliciti. PostgreSQL conserva lo stato autorevole del processo; Redis supporta coordinamento e capacità temporanee; le integrazioni esterne attraversano adapter tipizzati, transactional outbox, retry e riconciliazione.

## Obiettivo operativo

Il flusso completo previsto comprende:

1. importazione incrementale dell’ordine dal marketplace;
2. accettazione sul canale sorgente;
3. acquisizione e normalizzazione dell’indirizzo di spedizione;
4. persistenza idempotente di ordine e righe;
5. invio dell’ordine a Space tramite API;
6. aggiornamento della disponibilità;
7. picking barcode completo o parziale;
8. definizione di colli, peso reale, volumetrico e tariffabile;
9. creazione spedizione ed etichetta GLS;
10. restituzione di tracking e fulfilment al marketplace;
11. controllo operativo tramite audit, retry e riconciliazione.

## Stato del progetto

### Implementato e verificato

- bootstrap condiviso HTTP e CLI;
- configurazione ambiente validata e secret file centralizzati;
- trusted proxy espliciti;
- Kernel HTTP con correlation ID, error handling e header di sicurezza;
- logging JSON con redazione dei dati sensibili;
- health check liveness/readiness con PostgreSQL, Redis e verifica schema;
- PostgreSQL con migrazioni, `JSONB`, `TIMESTAMPTZ` e vincoli principali;
- Docker development e production;
- runtime applicativo separato dall’immagine migrazioni;
- CI con audit Composer, PostgreSQL, Redis, PHPUnit, PHPStan e smoke test production;
- contratti iniziali tipizzati per Marketplace, Space e GLS;
- distinzione tipizzata tra canale marketplace e connettore tecnico;
- canali futuri registrati per Amazon, eMAG, Temu e IBS, con SellRapido come connettore aggregatore;
- interfaccia operativa server-rendered, responsive e accessibile per tutte le aree previste;
- schermate di accesso, dashboard, ordini, picking, spedizioni, automazioni, integrazioni, audit, utenti e impostazioni;
- schema iniziale della transactional outbox;
- documentazione architetturale, sicurezza e roadmap.

### Prossima sequenza

La roadmap parte dalla composizione applicativa e dal dominio ordine:

1. container Dependency Injection compilato;
2. configurazioni tipizzate e Clock iniettato;
3. aggregato `Order` e macchina a stati deterministica;
4. repository PostgreSQL e transaction boundary;
5. scrittura di dominio e outbox nella stessa transazione;
6. prima vertical slice Marketplace → HAPA → Space.

Le integrazioni provider reali, il worker outbox, i casi d’uso di picking e GLS, l’autenticazione e il collegamento della UI a dati e azioni appartengono alle fasi successive descritte in [`docs/TODO.md`](docs/TODO.md). La strategia per SellRapido, Amazon, eMAG, Temu e IBS è definita in [`docs/MARKETPLACES.md`](docs/MARKETPLACES.md).

## Architettura

```text
app/
  Core/                    runtime e servizi trasversali
  Modules/                 dominio, casi d’uso, contratti e adapter
bin/
  console                  ingresso CLI
config/
  routes.php               composizione HTTP e routing
database/
  migrations/              schema PostgreSQL versionato
docs/
  ARCHITECTURE.md           riferimento architetturale
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

La UI è attualmente un layer di presentazione completo ma non espone dati reali o azioni mutative. Autenticazione, autorizzazione e collegamento ai casi d’uso restano gate obbligatori prima dell’esercizio operativo.

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
- smoke test HTTP della liveness.

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
| [`docs/INTERFACE.md`](docs/INTERFACE.md) | architettura UI, mappa delle schermate, accessibilità e sicurezza |
| [`docs/MARKETPLACES.md`](docs/MARKETPLACES.md) | canali futuri, connettori, gate di discovery e prevenzione dei duplicati |
| [`docs/SYMFONY_ALIGNMENT.md`](docs/SYMFONY_ALIGNMENT.md) | componenti Symfony adottati, esclusi o valutati |
| [`docs/SECURITY.md`](docs/SECURITY.md) | autenticazione, sessione, provider, worker, dati e produzione |
| [`docs/TODO.md`](docs/TODO.md) | sequenza esecutiva, gate e criterio end-to-end |

Ogni modifica a una decisione architetturale, a un requisito di sicurezza o alla sequenza di sviluppo aggiorna il documento corrispondente nello stesso changeset.

## Licenza e proprietà

Il codice e la documentazione sono proprietari. Utilizzo, distribuzione e modifica seguono gli accordi definiti dal proprietario del repository.
