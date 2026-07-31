# Aggiornamento della produzione

Lo script `scripts/production-update.sh` aggiorna i checkout `main` di HAPA e
HAPA Automation, ricostruisce le immagini Docker, applica le migrazioni e
riavvia i servizi rispettando le dipendenze.

Sul server di produzione:

```bash
sudo /usr/local/sbin/hapa-production-update --check
sudo /usr/local/sbin/hapa-production-update
```

Il controllo preliminare rifiuta repository diversi da `main`, modifiche
tracciate non committate, commit locali o divergenti, file `.env` mancanti e
configurazioni Compose non valide. Un lock impedisce due aggiornamenti
contemporanei. Il comando termina con errore se uno dei container o l'endpoint
pubblico non supera gli health check.

Le configurazioni locali del server restano esterne a Git:

- `/opt/hapa-stack/hapa/.env`
- `/opt/hapa-stack/hapa-automation/.env`
- `/opt/hapa-stack/hapa-automation/automation-server.yml`

Variabili opzionali:

```bash
HAPA_STACK_ROOT=/opt/hapa-stack
HAPA_READY_URL=https://admin.hapa.it/health/ready
LOCAL_READY_URL=http://127.0.0.1:8080/health/ready
HAPA_UPDATE_LOCK_FILE=/var/lock/hapa-production-update.lock
```
