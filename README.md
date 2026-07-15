# HAPA

HAPA è la piattaforma proprietaria per la gestione del ciclo ordine tra marketplace, Space API, magazzino e GLS.

## Flusso applicativo

Il processo previsto comprende:

1. accettazione dell’ordine sul marketplace;
2. recupero dell’indirizzo di spedizione;
3. importazione dell’ordine e delle righe;
4. invio dell’ordine a Space tramite API;
5. controllo della disponibilità della merce;
6. preparazione e picking barcode;
7. gestione degli ordini completi e parziali;
8. creazione della spedizione e dell’etichetta GLS;
9. invio del tracking al marketplace;
10. supervisione attraverso il pannello operativo.

I marketplace condividono lo stesso modello interno dell’ordine e vengono collegati tramite adapter dedicati. Space gestisce approvvigionamento e disponibilità; il magazzino gestisce picking e parziali; GLS gestisce spedizione, label e tracking.

## Architettura

La piattaforma utilizza un framework custom proprietario in PHP 8.4 con PostgreSQL, Redis, Nginx/PHP-FPM, Docker Compose, Phinx, PHPUnit, PHPStan, Monolog e componenti Symfony selezionati.

```text
app/Core       bootstrap e servizi trasversali
app/Modules    dominio e contratti applicativi
config         composizione e routing
database       migrazioni PostgreSQL
tests          test unitari, integration e architetturali
docker         immagini e configurazioni runtime
```

## Stato implementativo

La foundation comprende:

- bootstrap condiviso per HTTP e CLI;
- configurazione ambiente e secret file centralizzati;
- trusted proxy e trusted host per deployment dietro reverse proxy;
- error handling, logging JSON, correlation ID e redazione dei dati sensibili;
- health check live e ready per PostgreSQL, Redis e versione schema;
- schema iniziale per ordini, spedizioni, outbox, delivery esterne e audit;
- timestamp UTC, JSONB e vincoli di integrità PostgreSQL;
- configurazioni Docker distinte per sviluppo e produzione;
- immagini separate per runtime e migrazioni;
- rete dati isolata e connettività esterna del processo applicativo;
- CI con PostgreSQL, Redis, migrazioni, test, PHPStan, audit Composer e smoke test production;
- contratti tipizzati per Marketplace, Space e GLS.

Le vertical slice di business, gli adapter reali, l’autenticazione applicativa, il pannello operativo e il worker della transactional outbox appartengono alle fasi successive.

## Avvio locale

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec php composer install
docker compose exec php vendor/bin/phinx migrate -e development
```

```text
GET http://localhost:8080/
GET http://localhost:8080/health/live
GET http://localhost:8080/health/ready
```

## Verifiche

```bash
composer ci:fast
composer ci:full
```

La descrizione delle decisioni tecniche è in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). Deploy, migrazioni, health check e procedure operative sono in [`docs/OPERATIONS.md`](docs/OPERATIONS.md). La politica di sicurezza è in [`SECURITY.md`](SECURITY.md).
