# Operazioni HAPA

## Preparazione production

Copiare il template e sostituire tutti i riferimenti immagine con versioni associate al commit e digest SHA-256 verificati.

```bash
cp .env.production.example .env.production
install -d -m 0700 secrets
openssl rand -base64 48 > secrets/db_password.txt
openssl rand -base64 48 > secrets/redis_password.txt
chmod 0444 secrets/*.txt
```

Docker Compose monta i secret file in sola lettura esclusivamente nei servizi autorizzati. Il permesso `0444` consente la lettura agli UID non privilegiati dei container; la directory host `0700` impedisce agli altri utenti del server di raggiungere i file.

`APP_URL` identifica l’host pubblico HTTPS. `TRUSTED_PROXIES` identifica esclusivamente i proxy autorizzati a fornire gli header `X-Forwarded-*`. Il binding HTTP applicativo resta su loopback; TLS e HSTS vengono applicati dal reverse proxy o load balancer di frontiera.

## Gate CI

Il commit destinato al deploy deve avere verdi i job `quality`, `static-analysis` e `production-smoke`. Il job production costruisce le immagini, avvia lo stack, applica le migrazioni e verifica liveness e readiness.

## Build e deploy

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml config
docker compose --env-file .env.production -f docker-compose.prod.yml build php migration nginx redis
docker compose --env-file .env.production -f docker-compose.prod.yml up -d --wait postgres redis php nginx
```

Il processo PHP appartiene sia alla rete applicativa sia alla rete dati. PostgreSQL e Redis appartengono esclusivamente alla rete dati interna. Questa separazione mantiene i datastore isolati e consente agli adapter applicativi di raggiungere i provider esterni.

## Migrazioni

Le migrazioni utilizzano l’immagine dedicata `migration`:

```bash
docker compose --env-file .env.production -f docker-compose.prod.yml \
  run --rm migration migrate -e production
```

L’endpoint readiness rimane indisponibile finché la versione registrata in `phinxlog` non raggiunge `SchemaVersion::LATEST`.

## Health check

- `/nginx-health`: liveness interna del container Nginx;
- `/health/live`: processo HTTP applicativo disponibile;
- `/health/ready`: PostgreSQL, Redis e schema applicativo disponibili.

La readiness è accessibile esclusivamente da reti private. In produzione restituisce lo stato aggregato e omette il dettaglio dei componenti.

## Verifica post-deploy

```bash
curl --fail -H 'Host: hapa.example.com' http://127.0.0.1:8080/health/live
curl --fail -H 'Host: hapa.example.com' http://127.0.0.1:8080/health/ready
docker compose --env-file .env.production -f docker-compose.prod.yml ps
```

## Rollback

Il rollback applicativo utilizza immagini identificate da commit. Le migrazioni distruttive richiedono una procedura dedicata e un backup verificato. Il comando `phinx rollback` viene eseguito soltanto dopo verifica della reversibilità della migrazione interessata.

## Backup e ripristino

Il piano operativo deve includere:

- backup PostgreSQL cifrato e monitorato;
- verifica periodica del ripristino su ambiente isolato;
- conservazione coerente con la retention dei dati personali;
- backup delle label quando la loro rigenerazione dal provider non è garantita;
- esclusione dei secret file dagli artifact e dai backup applicativi ordinari.

## Incidenti

Ogni incidente viene correlato tramite `X-Correlation-ID`. I log production registrano classe e codice dell’eccezione, omettendo il messaggio tecnico grezzo. La procedura comprende contenimento, rotazione dei segreti, riconciliazione ordini, ripristino e verifica post-incidente.
