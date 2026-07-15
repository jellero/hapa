# Architettura HAPA

## Obiettivo

HAPA gestisce il ciclo ordine tra marketplace generici, Space API, magazzino e GLS. Il gestionale mantiene lo stato autorevole dell’ordine; le integrazioni esterne vengono isolate attraverso contratti e adapter.

## Foundation

Il framework custom proprietario utilizza PHP 8.4, PostgreSQL, Redis, Docker, Nginx/PHP-FPM, Phinx, PHPUnit, PHPStan, Monolog e componenti Symfony selezionati.

Il namespace applicativo è `Hapa\`.

## Confini

- `app/Core`: runtime HTTP, console, configurazione, database, logging, health check e servizi trasversali.
- `app/Modules`: dominio e contratti applicativi.
- `config`: composizione applicativa e routing.
- `database/migrations`: schema PostgreSQL versionato.
- `tests`: test unitari, integration e architetturali.

Il Core rimane indipendente dai moduli applicativi. Le dipendenze tra moduli attraversano contratti espliciti.

## Moduli di dominio

- `Orders`: ordine, righe, quantità e transizioni di stato.
- `Marketplace`: import, accettazione, indirizzo e tracking.
- `Space`: invio ordine e disponibilità merce.
- `Warehouse` / `Picking`: preparazione e scansione barcode.
- `PartialOrders`: quantità spedite, annullate e approvazioni.
- `Gls`: spedizione, label e tracking.
- `Automation`: outbox, job, retry e riconciliazione.
- `OperationalDashboard`: visibilità e azioni operative.

I contratti iniziali Marketplace, Space e GLS sono presenti. Le implementazioni dei provider verranno introdotte per vertical slice.

## Foundation implementata

- bootstrap HTTP e CLI;
- routing e risposte JSON;
- configurazione ambiente validata;
- blocco dell’avvio production con debug, URL non HTTPS o segreti deboli;
- secret file per PostgreSQL e Redis;
- exception handling centralizzato con messaggi tecnici esclusi dai log production;
- logging JSON su stderr;
- correlation ID propagato nelle risposte;
- redazione dei dati sensibili nei contesti di log;
- health live e ready, con dettaglio componenti escluso in produzione;
- connessioni PostgreSQL con timeout e prepared statement native;
- schema ordine, spedizione, outbox, delivery esterna e audit;
- check constraint su stati, disponibilità, quantità, pesi e tentativi;
- test di coerenza tra stati PHP e vincolo PostgreSQL;
- Docker development e production distinti;
- CI con migrazioni reali PostgreSQL, test, analisi statica, audit dipendenze e action pinning.

## Affidabilità delle integrazioni

Lo schema della transactional outbox contiene idempotency key, tentativi, disponibilità temporale, lock, worker identity e stati terminali. Il worker verrà attivato insieme ai primi handler reali, utilizzando claim atomici PostgreSQL con `FOR UPDATE SKIP LOCKED`.

Ogni integrazione dovrà applicare:

- idempotency key;
- correlation ID;
- timeout esplicito;
- classificazione successo / temporaneo / definitivo;
- retry con backoff;
- persistenza dei tentativi;
- redazione dei dati sensibili prima del logging o della persistenza tecnica;
- riconciliazione e gestione manuale degli errori definitivi.

## Sicurezza

La configurazione production richiede HTTPS e secret file esterni al repository. Il servizio HTTP viene associato a loopback; la terminazione TLS e HSTS appartengono al reverse proxy o load balancer di frontiera. Le immagini production utilizzano build multistage, utente non privilegiato, filesystem read-only, capability ridotte e reti interne per PostgreSQL e Redis.

Nginx inoltra a PHP-FPM esclusivamente il front controller, limita la readiness alle reti private e applica header di sicurezza. Le risposte applicative mantengono messaggi generici in produzione e includono un correlation ID.

I payload tecnici contenenti dati personali richiederanno policy di minimizzazione, redazione e retention prima dell’attivazione degli adapter reali.

## Scalabilità

Processi HTTP, PostgreSQL, Redis e futuri worker sono distribuibili separatamente. La capacità asincrona crescerà tramite replica dei worker e claim concorrenti controllati. Adapter o workload specifici potranno evolvere in servizi dedicati mantenendo stabili i contratti del dominio.
