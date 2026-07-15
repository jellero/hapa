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

I marketplace vengono integrati tramite adapter dedicati e condividono lo stesso modello interno dell’ordine. Space gestisce approvvigionamento e disponibilità; il magazzino gestisce picking e parziali; GLS gestisce spedizione, label e tracking.

## Architettura

La piattaforma utilizza un framework custom proprietario in PHP 8.4. Lo stack comprende:

- PostgreSQL;
- Redis;
- Nginx e PHP-FPM;
- Docker Compose;
- Phinx;
- PHPUnit;
- PHPStan;
- componenti Symfony selezionati;
- Monolog con output JSON e redazione dei dati sensibili.

Il codice è organizzato in:

```text
app/Core       runtime e servizi trasversali
app/Modules    dominio e contratti applicativi
config         composizione e routing
database       migrazioni PostgreSQL
tests          test unitari, integration e architetturali
docker         immagini e configurazioni runtime
```

## Stato implementativo

La foundation attuale comprende:

- bootstrap HTTP e console;
- routing;
- validazione della configurazione ambiente;
- gestione centralizzata delle eccezioni;
- logging JSON con correlation ID;
- redazione ricorsiva dei campi sensibili nei log;
- health check live e ready per PostgreSQL e Redis;
- schema iniziale per ordini, spedizioni, outbox, delivery esterne e audit;
- vincoli database su stati, disponibilità e quantità;
- configurazioni Docker distinte per sviluppo e produzione;
- immagine PHP production multistage e non privilegiata;
- secret file per PostgreSQL e Redis;
- pipeline CI con migrazioni, test, PHPStan e audit Composer;
- contratti iniziali per Marketplace, Space e GLS.

Le vertical slice di business, gli adapter reali, l’autenticazione applicativa, il pannello operativo e il worker della transactional outbox appartengono alle fasi successive. Lo schema outbox è presente; l’elaborazione viene attivata insieme ai primi handler applicativi.

## Avvio locale

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec php composer install
docker compose exec php vendor/bin/phinx migrate -e development
```

Endpoint:

```text
GET http://localhost:8080/
GET http://localhost:8080/health/live
GET http://localhost:8080/health/ready
```

Diagnostica:

```bash
docker compose exec php php bin/console system:check
```

## Verifiche

```bash
composer validate --strict
composer audit --locked
composer architecture:check
composer test
composer analyse
```

## Produzione

La configurazione production utilizza HTTPS e secret file dedicati per PostgreSQL e Redis. Il template è `.env.production.example`.

```bash
cp .env.production.example .env.production
mkdir -p secrets
umask 077
openssl rand -base64 48 > secrets/db_password.txt
openssl rand -base64 48 > secrets/redis_password.txt
```

Il container Nginx viene pubblicato per impostazione predefinita su `127.0.0.1:8080`. Un reverse proxy o load balancer esterno deve terminare TLS, inoltrare il traffico verso tale endpoint e applicare HSTS. Il backend PostgreSQL/Redis resta su rete Docker interna; `/health/ready` è accessibile soltanto da indirizzi privati.

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml config
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d
```

Le migrazioni vengono eseguite come passaggio controllato di deploy:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml run --rm php \
  vendor/bin/phinx migrate -e production
```

La documentazione architetturale è disponibile in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md); la politica di sicurezza è descritta in [`SECURITY.md`](SECURITY.md).
