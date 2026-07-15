# HAPA

HAPA è la piattaforma proprietaria per la gestione del ciclo ordine tra marketplace, Space API, magazzino e GLS.

## Flusso applicativo

Il processo previsto comprende accettazione dell’ordine, recupero dell’indirizzo, importazione, invio a Space, verifica disponibilità, picking barcode, gestione completa o parziale, creazione spedizione GLS e restituzione del tracking al marketplace.

I marketplace vengono integrati tramite adapter dedicati e condividono lo stesso modello interno dell’ordine.

## Architettura

La piattaforma utilizza un framework custom proprietario in PHP 8.4 con PostgreSQL, Redis, Nginx/PHP-FPM, Docker Compose, Phinx, PHPUnit, PHPStan, Monolog e componenti Symfony selezionati.

```text
app/Core       runtime e servizi trasversali
app/Modules    dominio e contratti applicativi
config         composizione e routing
database       migrazioni PostgreSQL
tests          test unitari e integration
docker         immagini e configurazioni runtime
```

La foundation attuale comprende bootstrap condiviso HTTP/CLI, configurazione e secret centralizzati, trusted proxy, logging JSON, correlation ID, redazione dei dati sensibili, health check con verifica schema, PostgreSQL con JSONB e TIMESTAMPTZ, Docker development/production, immagine migrazioni dedicata, CI con test PostgreSQL/Redis e smoke test production, contratti tipizzati per Marketplace, Space e GLS.

Le vertical slice di business, l’autenticazione applicativa, il pannello operativo e il worker della transactional outbox appartengono alle fasi successive.

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
composer ci:fast
composer ci:full
```

`ci:fast` esegue controlli architetturali, test e PHPStan. `ci:full` aggiunge validazione Composer e audit delle dipendenze. La pipeline GitHub esegue inoltre migrazioni reali, Redis integration test e smoke test dello stack production.

## Produzione

La configurazione production utilizza HTTPS, trusted proxy espliciti, immagini associate al commit e secret file dedicati per PostgreSQL e Redis.

```bash
cp .env.production.example .env.production
mkdir -p secrets
umask 077
openssl rand -base64 48 > secrets/db_password.txt
openssl rand -base64 48 > secrets/redis_password.txt
```

Impostare `IMAGE_TAG` con il commit da distribuire e configurare `TRUSTED_PROXIES` con gli indirizzi effettivi del reverse proxy.

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml config
docker compose --env-file .env.production -f docker-compose.prod.yml build
docker compose --env-file .env.production -f docker-compose.prod.yml up -d postgres redis
docker compose --env-file .env.production -f docker-compose.prod.yml \
  --profile tools run --rm migration
docker compose --env-file .env.production -f docker-compose.prod.yml up -d php nginx
```

Nginx viene pubblicato per impostazione predefinita su `127.0.0.1:8080`. Il reverse proxy esterno gestisce TLS e HSTS. PostgreSQL e Redis restano sulla rete interna; `/health/ready` è limitato alle reti private.

La documentazione architetturale è in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md); la politica di sicurezza è in [`SECURITY.md`](SECURITY.md).
