# Architettura HAPA

## Obiettivo

HAPA gestisce il ciclo ordine tra marketplace generici, Space API, magazzino e GLS. Il dominio applicativo mantiene lo stato autorevole dell’ordine; le integrazioni esterne espongono adapter tipizzati e idempotenti.

## Foundation proprietaria

La base deriva dal framework custom proprietario già impiegato in produzione per applicazioni gestionali PHP. Sono stati riutilizzati stack, convenzioni e confini architetturali: PHP 8.4, PostgreSQL, Redis, Docker, Nginx/PHP-FPM, Phinx, PHPUnit, PHPStan e componenti Symfony selezionati.

Il namespace applicativo è `Hapa\\`. I moduli PMS restano fuori dal repository.

## Confini

- `app/Core`: runtime HTTP, console, database, configurazione e servizi trasversali.
- `app/Modules`: dominio e integrazioni applicative.
- `config`: composizione applicativa e routing.
- `database/migrations`: schema PostgreSQL versionato.
- `tests`: test unitari e architetturali.

Il Core non dipende dai moduli applicativi. Le dipendenze tra moduli passano attraverso contratti espliciti.

## Moduli iniziali

- `Orders`: ordine, righe, quantità e macchina a stati.
- `Marketplace`: import, accettazione, indirizzo e tracking.
- `Space`: invio ordine e disponibilità merce.
- `Warehouse` / `Picking`: preparazione e scansione barcode.
- `PartialOrders`: quantità spedite, annullate e approvazioni.
- `Gls`: spedizione, label e tracking.
- `Automation`: outbox, job, retry e riconciliazione.
- `OperationalDashboard`: visibilità e azioni operative.

## Affidabilità

Le mutazioni di dominio e la pubblicazione degli eventi condividono la stessa transazione PostgreSQL. La transactional outbox separa il commit di business dalla consegna tecnica.

Ogni chiamata esterna utilizza:

- idempotency key;
- correlation ID;
- timeout esplicito;
- classificazione successo / temporaneo / definitivo;
- tentativi persistenti;
- request e response log con redazione dei dati sensibili;
- retry con backoff;
- verifica manuale per gli errori definitivi.

I worker concorrenti useranno claim atomici PostgreSQL con `FOR UPDATE SKIP LOCKED`.

## Scalabilità

Processi HTTP, worker, scheduler, PostgreSQL e Redis sono distribuibili separatamente. La replica orizzontale dei worker aumenta la capacità asincrona mantenendo idempotenza e locking applicativo. Adapter o workload specifici possono evolvere in servizi dedicati conservando i contratti del dominio.
