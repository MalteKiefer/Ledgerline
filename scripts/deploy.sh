#!/usr/bin/env bash
#
# Ledgerline Docker rolling deploy. Run from the stack dir (the repo clone,
# e.g. /srv/ledgerline) where docker-compose.yml + .env live.
#
#   ./scripts/deploy.sh                 # pick a version (default: latest tag), roll out
#   ./scripts/deploy.sh deploy 1.207.5  # roll out a specific tag/branch
#   ./scripts/deploy.sh rollback        # switch back to the previous image tag
#   ./scripts/deploy.sh logs [service]  # follow logs
#   ./scripts/deploy.sh status          # compose ps + health
#
# Rolling: checkout the ref, build image tagged with it, recreate the app/worker/
# scheduler containers, wait for health, prune. Migrations + caches run on app
# start (serversideup AUTORUN, migrate is lock-isolated). The previous image tag
# is remembered for one-command rollback.
#
set -Eeuo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"   # stack dir = repo root

STATE_DIR=".deploy"
LAST_TAG_FILE="${STATE_DIR}/last_image_tag"
LOG_FILE="${STATE_DIR}/deploy-$(date +%Y%m%d-%H%M%S).log"
mkdir -p "$STATE_DIR"

if [[ -t 1 ]]; then C_B=$'\033[1;34m'; C_G=$'\033[1;32m'; C_Y=$'\033[1;33m'; C_R=$'\033[1;31m'; C_0=$'\033[0m'; else C_B=; C_G=; C_Y=; C_R=; C_0=; fi
_ts() { date '+%Y-%m-%d %H:%M:%S'; }
log()  { printf '%s%s %s%s\n' "$C_B" "$(_ts)" "$*" "$C_0" | tee -a "$LOG_FILE" >&2; }
ok()   { printf '%s%s  ✓ %s%s\n' "$C_G" "$(_ts)" "$*" "$C_0" | tee -a "$LOG_FILE" >&2; }
warn() { printf '%s%s  ! %s%s\n' "$C_Y" "$(_ts)" "$*" "$C_0" | tee -a "$LOG_FILE" >&2; }
die()  { printf '%s%s  ✗ %s%s\n' "$C_R" "$(_ts)" "$*" "$C_0" | tee -a "$LOG_FILE" >&2; exit 1; }
trap 'die "failed — see ${LOG_FILE}. Fix and re-run, or: $0 rollback"' ERR

DC() { docker compose "$@"; }

preflight() {
    command -v docker >/dev/null || die "docker not installed"
    docker compose version >/dev/null 2>&1 || die "docker compose v2 required"
    [[ -f docker-compose.yml ]] || die "docker-compose.yml not found (run from the stack dir)"
    [[ -f .env ]] || die ".env missing — copy .env.docker.example to .env and fill it"
    grep -qE '^APP_KEY=base64:' .env || warn "APP_KEY not set — generate: docker compose run --rm app php artisan key:generate --show"
    git rev-parse --git-dir >/dev/null 2>&1 || die "not a git checkout"
}

latest_tag() { git tag --sort=-v:refname | head -1; }

set_image_tag() { # persist chosen tag into .env for compose interpolation
    local tag="$1"
    if grep -qE '^IMAGE_TAG=' .env; then
        sed -i.bak "s|^IMAGE_TAG=.*|IMAGE_TAG=${tag}|" .env && rm -f .env.bak
    else
        printf '\nIMAGE_TAG=%s\n' "$tag" >> .env
    fi
}
current_tag() { grep -E '^IMAGE_TAG=' .env | cut -d= -f2- || echo local; }

wait_healthy() {
    local svc="$1" tries="${2:-30}" i state
    for ((i = 1; i <= tries; i++)); do
        state="$(docker inspect -f '{{ if .State.Health }}{{ .State.Health.Status }}{{ else }}{{ .State.Status }}{{ end }}' \
            "$(DC ps -q "$svc" 2>/dev/null)" 2>/dev/null || echo missing)"
        case "$state" in
            healthy|running) ok "${svc}: ${state}"; return 0 ;;
            unhealthy)       die "${svc} became unhealthy — check: $0 logs ${svc}" ;;
        esac
        sleep 3
    done
    die "${svc} did not become healthy in time — check: $0 logs ${svc}"
}

# Pre-deploy Postgres snapshot so a bad forward-only migration is recoverable
# (auto-migrations aren't reverted on rollback). Local socket dump, newest 3 kept.
snapshot_db() {
    local tag="$1" ts u d; ts="$(date -u +%Y%m%dT%H%M%SZ)"
    u="$(grep -E '^DB_USERNAME=' .env | cut -d= -f2-)"; u="${u:-ledgerline}"
    d="$(grep -E '^DB_DATABASE=' .env | cut -d= -f2-)"; d="${d:-ledgerline}"
    mkdir -p db-snapshots
    local i; for i in $(seq 1 30); do DC exec -T db pg_isready -U "$u" >/dev/null 2>&1 && break; sleep 1; done
    log "pre-deploy DB snapshot (rollback safety)"
    if DC exec -T db pg_dump -U "$u" "$d" 2>>"$LOG_FILE" | gzip > "db-snapshots/pre-${tag}-${ts}.sql.gz"; then
        chmod 600 "db-snapshots/pre-${tag}-${ts}.sql.gz"
        ls -1t db-snapshots/pre-*.sql.gz 2>/dev/null | tail -n +4 | xargs -r rm -f
    else
        warn "DB snapshot failed — continuing (roll back the app image manually if a migration breaks)"
        rm -f "db-snapshots/pre-${tag}-${ts}.sql.gz"
    fi
}

# Append a deploy provenance record (what ran, when, from which image id).
record_provenance() {
    local ref="$1" tag="$2"
    printf '%s\tref=%s\timage=ledgerline:%s\tid=%s\n' \
        "$(date -u +%FT%TZ)" "$ref" "$tag" \
        "$(docker image inspect --format '{{.Id}}' "ledgerline:${tag}" 2>/dev/null)" \
        >> deploy-provenance.log
}

cmd_deploy() {
    preflight
    local ref="${1:-}"
    git fetch --all --tags --prune >>"$LOG_FILE" 2>&1
    if [[ -z "$ref" ]]; then
        local def; def="$(latest_tag)"
        log "recent tags:"; git tag --sort=-v:refname | head -8 | sed 's/^/    /' | tee -a "$LOG_FILE" >&2
        read -r -p "Version to deploy [${def}]: " ref </dev/tty || true
        ref="${ref:-$def}"
    fi
    git rev-parse --verify "${ref}^{commit}" >/dev/null 2>&1 || die "ref '${ref}' not found"

    log "checking out ${ref}"
    git checkout --quiet --detach "$ref"

    local prev; prev="$(current_tag)"
    local tag="${ref//\//_}"
    echo "$prev" > "$LAST_TAG_FILE"
    set_image_tag "$tag"

    log "building image ledgerline:${tag}"
    DC build >>"$LOG_FILE" 2>&1

    log "bringing up datastores"
    DC up -d db valkey >>"$LOG_FILE" 2>&1

    # Snapshot the DB before the app starts (migrations run on app start).
    snapshot_db "$tag"

    log "rolling out app/worker/scheduler (migrations run on app start)"
    DC up -d >>"$LOG_FILE" 2>&1

    wait_healthy app
    DC restart worker scheduler >>"$LOG_FILE" 2>&1

    log "pruning dangling images"
    docker image prune -f >>"$LOG_FILE" 2>&1 || true
    record_provenance "$ref" "$tag"
    ok "deployed ${ref} (image ledgerline:${tag}). Previous tag: ${prev}"
}

cmd_rollback() {
    preflight
    [[ -f "$LAST_TAG_FILE" ]] || die "no previous tag recorded"
    local prev; prev="$(cat "$LAST_TAG_FILE")"
    warn "rolling back to image ledgerline:${prev}"
    set_image_tag "$prev"
    DC up -d >>"$LOG_FILE" 2>&1
    wait_healthy app
    ok "rolled back to ${prev}"
}

case "${1:-deploy}" in
    deploy)   shift || true; cmd_deploy "${1:-}" ;;
    rollback) cmd_rollback ;;
    logs)     shift || true; DC logs -f --tail=200 "${@:-}" ;;
    status)   DC ps ;;
    *)        sed -n '2,20p' "$0" | sed 's/^# \{0,1\}//' ;;
esac
