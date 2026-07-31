#!/usr/bin/env bash

set -Eeuo pipefail

SCRIPT_NAME="$(basename "$0")"
readonly SCRIPT_NAME
readonly STACK_ROOT="${HAPA_STACK_ROOT:-/opt/hapa-stack}"
readonly HAPA_DIR="${HAPA_DIR:-${STACK_ROOT}/hapa}"
readonly AUTOMATION_DIR="${AUTOMATION_DIR:-${STACK_ROOT}/hapa-automation}"
readonly HAPA_ENV_FILE="${HAPA_ENV_FILE:-${HAPA_DIR}/.env}"
readonly AUTOMATION_ENV_FILE="${AUTOMATION_ENV_FILE:-${AUTOMATION_DIR}/.env}"
readonly AUTOMATION_OVERRIDE="${AUTOMATION_OVERRIDE:-${AUTOMATION_DIR}/automation-server.yml}"
readonly HAPA_READY_URL="${HAPA_READY_URL:-https://admin.hapa.it/health/ready}"
readonly LOCAL_READY_URL="${LOCAL_READY_URL:-http://127.0.0.1:8080/health/ready}"
readonly LOCK_FILE="${HAPA_UPDATE_LOCK_FILE:-/var/lock/hapa-production-update.lock}"

CHECK_ONLY=false
STARTED_AT="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

usage() {
  cat <<EOF
Uso: ${SCRIPT_NAME} [--check] [--help]

Aggiorna i checkout main di HAPA e HAPA Automation, ricostruisce le immagini,
applica le migrazioni e riavvia in sicurezza lo stack Docker di produzione.

Opzioni:
  --check   Esegue solo i controlli preliminari e mostra le revisioni.
  --help    Mostra questo messaggio.

Variabili principali:
  HAPA_STACK_ROOT          Default: /opt/hapa-stack
  HAPA_READY_URL           Default: https://admin.hapa.it/health/ready
  LOCAL_READY_URL          Default: http://127.0.0.1:8080/health/ready
  HAPA_UPDATE_LOCK_FILE    Default: /var/lock/hapa-production-update.lock
EOF
}

log() {
  printf '[%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"
}

fail() {
  log "ERRORE: $*" >&2
  exit 1
}

on_error() {
  local exit_code=$?
  log "Aggiornamento interrotto (exit ${exit_code}). Controllare i log Docker prima di riprovare." >&2
  exit "$exit_code"
}

trap on_error ERR

while (($# > 0)); do
  case "$1" in
    --check)
      CHECK_ONLY=true
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      usage >&2
      fail "Opzione sconosciuta: $1"
      ;;
  esac
  shift
done

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Comando richiesto non trovato: $1"
}

require_file() {
  [[ -f "$1" ]] || fail "File richiesto non trovato: $1"
}

require_repository() {
  local directory="$1"
  [[ -d "${directory}/.git" ]] || fail "Repository Git non trovato: ${directory}"
  [[ "$(git -C "$directory" branch --show-current)" == "main" ]] \
    || fail "Il repository ${directory} non è sul branch main."
  [[ -z "$(git -C "$directory" status --porcelain --untracked-files=no)" ]] \
    || fail "Il repository ${directory} contiene modifiche tracciate non committate."
}

compose_automation() {
  local files=(--env-file "$AUTOMATION_ENV_FILE" -f "${AUTOMATION_DIR}/docker-compose.yml")
  if [[ -f "$AUTOMATION_OVERRIDE" ]]; then
    files+=(-f "$AUTOMATION_OVERRIDE")
  fi
  docker compose "${files[@]}" "$@"
}

compose_hapa() {
  docker compose --env-file "$HAPA_ENV_FILE" -f "${HAPA_DIR}/docker-compose.prod.yml" "$@"
}

set_env_value() {
  local file="$1"
  local key="$2"
  local value="$3"
  if grep -q "^${key}=" "$file"; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$file"
  else
    printf '%s=%s\n' "$key" "$value" >>"$file"
  fi
}

wait_for_url() {
  local url="$1"
  local attempts="${2:-30}"
  local delay="${3:-2}"
  local attempt
  for ((attempt = 1; attempt <= attempts; attempt++)); do
    if curl --fail --silent --show-error --max-time 10 "$url" >/dev/null; then
      return 0
    fi
    sleep "$delay"
  done
  fail "Health check non superato: ${url}"
}

remote_main_revision() {
  local directory="$1"
  git -C "$directory" ls-remote --exit-code origin refs/heads/main | awk '{print $1}'
}

update_repository() {
  local directory="$1"
  local label="$2"
  local before
  before="$(git -C "$directory" rev-parse HEAD)"
  log "Aggiorno ${label} da origin/main (attuale ${before:0:12})."
  git -C "$directory" fetch --prune origin
  git -C "$directory" merge-base --is-ancestor HEAD origin/main \
    || fail "${label}: il checkout contiene commit locali o diverge da origin/main."
  git -C "$directory" merge --ff-only origin/main
  local after
  after="$(git -C "$directory" rev-parse HEAD)"
  [[ "$after" == "$(git -C "$directory" rev-parse origin/main)" ]] \
    || fail "${label}: la revisione finale non coincide con origin/main."
  log "${label}: ${before:0:12} -> ${after:0:12}."
}

preflight() {
  require_command git
  require_command docker
  require_command curl
  require_command awk
  require_command flock
  docker compose version >/dev/null
  docker info >/dev/null

  require_repository "$HAPA_DIR"
  require_repository "$AUTOMATION_DIR"
  require_file "$HAPA_ENV_FILE"
  require_file "$AUTOMATION_ENV_FILE"
  require_file "${HAPA_DIR}/docker-compose.prod.yml"
  require_file "${AUTOMATION_DIR}/docker-compose.yml"

  compose_hapa config --quiet
  compose_automation config --quiet

  log "HAPA locale: $(git -C "$HAPA_DIR" rev-parse --short=12 HEAD)"
  log "HAPA origin/main: $(remote_main_revision "$HAPA_DIR" | cut -c1-12)"
  log "Automation locale: $(git -C "$AUTOMATION_DIR" rev-parse --short=12 HEAD)"
  log "Automation origin/main: $(remote_main_revision "$AUTOMATION_DIR" | cut -c1-12)"
}

deploy_automation() {
  log "Ricostruisco HAPA Automation."
  compose_automation build admin-api worker
  compose_automation --profile tools build migration

  log "Avvio database e RabbitMQ Automation."
  compose_automation up -d --wait postgres rabbitmq

  log "Applico le migrazioni Automation."
  compose_automation --profile tools run --rm migration

  log "Riavvio API e worker Automation."
  compose_automation up -d --force-recreate --wait admin-api worker
}

deploy_hapa() {
  local image_tag
  image_tag="$(git -C "$HAPA_DIR" rev-parse --short=7 HEAD)"
  set_env_value "$HAPA_ENV_FILE" IMAGE_TAG "$image_tag"
  chmod 0600 "$HAPA_ENV_FILE"

  log "Ricostruisco HAPA con IMAGE_TAG=${image_tag}."
  compose_hapa build php nginx redis
  compose_hapa --profile tools build migration

  log "Avvio PostgreSQL e Redis HAPA."
  compose_hapa up -d --wait postgres redis

  log "Applico le migrazioni HAPA."
  compose_hapa --profile tools run --rm migration

  log "Riavvio applicazione e processi di messaggistica HAPA."
  compose_hapa --profile messaging up -d --force-recreate --wait \
    php nginx outbox-relay inbox-consumer
}

verify_deployment() {
  log "Verifico i servizi interni."
  compose_hapa exec -T php php bin/console system:check
  wait_for_url "$LOCAL_READY_URL" 30 2
  wait_for_url "$HAPA_READY_URL" 45 2

  compose_hapa --profile messaging ps
  compose_automation ps

  log "HAPA attivo: $(git -C "$HAPA_DIR" rev-parse --short=12 HEAD)"
  log "Automation attiva: $(git -C "$AUTOMATION_DIR" rev-parse --short=12 HEAD)"
}

exec 9>"$LOCK_FILE"
flock -n 9 || fail "Un altro aggiornamento di produzione è già in corso."

log "Avvio controlli produzione."
preflight
if [[ "$CHECK_ONLY" == true ]]; then
  log "Controlli completati: nessuna modifica applicata."
  exit 0
fi

update_repository "$AUTOMATION_DIR" "HAPA Automation"
update_repository "$HAPA_DIR" "HAPA"
compose_automation config --quiet
compose_hapa config --quiet
deploy_automation
deploy_hapa
verify_deployment

log "Aggiornamento completato. Inizio=${STARTED_AT}, fine=$(date -u +%Y-%m-%dT%H:%M:%SZ)."
