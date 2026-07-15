# Architettura HAPA

## Obiettivo

HAPA gestisce il ciclo ordine tra marketplace generici, Space API, magazzino e GLS. Il gestionale mantiene lo stato autorevole dell’ordine; i provider esterni vengono isolati attraverso contratti tipizzati e adapter.

## Foundation

Il framework custom proprietario utilizza PHP 8.4, PostgreSQL, Redis, Docker, Nginx/PHP-FPM, Phinx, PHPUnit, PHPStan, Monolog e componenti Symfony selezionati. Il namespace applicativo è `Hapa\`.

## Confini

- `app/Core`: bootstrap, runtime HTTP, console, configurazione, database, logging e health check;
- `app/Modules`: dominio e contratti applicativi;
- `config`: composizione e routing;
- `database/migrations`: schema PostgreSQL versionato;
- `tests`: test unitari, integration e architetturali.

Il Core rimane indipendente dai moduli. Le dipendenze tra moduli attraversano esclusivamente contratti espliciti. Lo script architetturale costituisce l’unica implementazione delle regole automatiche sui namespace e sui confini.

## Composizione applicativa

HTTP e CLI utilizzano lo stesso `Bootstrap`. Il bootstrap carica l’ambiente, valida la configurazione, imposta UTC/timezone, configura trusted proxy e trusted host, crea logger, connection factory e readiness check, quindi restituisce un `ApplicationContext` condiviso.

Le route ricevono il contesto dalla composizione e non costruiscono direttamente dipendenze infrastrutturali. Questa struttura prepara l’introduzione del dependency injection container mantenendo una sola radice di composizione.

## Moduli di dominio

- `Orders`: ordine, righe, quantità e transizioni di stato;
- `Marketplace`: import, accettazione, indirizzo e tracking;
- `Space`: invio ordine e disponibilità merce;
- `Warehouse` / `Picking`: preparazione e scansione barcode;
- `PartialOrders`: quantità spedite, annullate e approvazioni;
- `Gls`: spedizione, label e tracking;
- `Automation`: outbox, job, retry e riconciliazione;
- `OperationalDashboard`: visibilità e azioni operative.

I contratti Marketplace, Space e GLS utilizzano DTO immutabili. Le implementazioni dei provider verranno introdotte per vertical slice.

## Persistenza

PostgreSQL utilizza connessioni impostate in UTC, timestamp con timezone e JSONB per i payload strutturati. Check constraint e indici univoci proteggono stati, quantità, tracking, identificativi esterni, tentativi e codici HTTP.

`SchemaVersion::LATEST` rappresenta la versione minima richiesta dal runtime. La readiness verifica la tabella `phinxlog` e blocca il traffico pronto quando il deployment applicativo precede le migrazioni.

## Affidabilità delle integrazioni

Lo schema della transactional outbox contiene idempotency key, tentativi, disponibilità temporale, lock, worker identity e stati terminali. Il worker verrà attivato insieme ai primi handler reali, usando claim atomici PostgreSQL con `FOR UPDATE SKIP LOCKED`.

Ogni integrazione applicherà idempotenza, correlation ID, timeout, classificazione degli errori, retry con backoff, persistenza dei tentativi, redazione dei dati sensibili e riconciliazione manuale degli esiti definitivi.

## Sicurezza e deployment

La configurazione production richiede HTTPS, secret file, tag applicativi associati al commit e riferimenti base tramite digest. Trusted proxy e host vengono configurati esplicitamente.

Nginx inoltra esclusivamente il front controller. PostgreSQL e Redis appartengono a una rete dati interna; PHP appartiene anche alla rete applicativa per raggiungere i provider esterni. Runtime HTTP e migrazioni usano target Docker separati.

La CI esegue PostgreSQL e Redis reali, migrazioni, test, analisi statica, audit Composer e uno smoke test dell’intero Compose production.

## Scalabilità

Processi HTTP, datastore e futuri worker sono distribuibili separatamente. La capacità asincrona crescerà tramite replica dei worker e claim concorrenti controllati. Adapter o workload specifici potranno evolvere in servizi dedicati mantenendo stabili i contratti del dominio.
