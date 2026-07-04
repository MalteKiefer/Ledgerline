#!/usr/bin/env bash
#
# Ledgerline server provisioning + rolling deploy for Debian 13 (trixie).
#
# Prepares a complete self-hosted environment (PHP-FPM, PostgreSQL, Valkey,
# Caddy w/ auto-TLS, Node, ffmpeg + HEIC, queue worker + scheduler) and rolls
# out a git ref as an atomic release (zero-ish downtime, symlink swap).
#
# Everything is idempotent and step-based: re-run the whole thing safely, or
# re-run a single step after a failure. All output is logged with timestamps.
#
# Usage:
#   sudo ./scripts/server.sh                 # provision (if needed) then deploy
#   sudo ./scripts/server.sh provision       # full environment setup only
#   sudo ./scripts/server.sh deploy [REF]    # roll out a git tag/branch
#   sudo ./scripts/server.sh doctor          # verify the environment
#   sudo ./scripts/server.sh steps           # list individual steps
#   sudo ./scripts/server.sh step <name>     # run one provisioning step
#   sudo ./scripts/server.sh rollback        # switch back to the previous release
#
# Config is saved to /etc/ledgerline/deploy.conf and reused; missing values are
# prompted interactively (or preseed via env vars of the same name). When the
# environment is already provisioned, a plain run only asks for the version.
#
set -Eeuo pipefail

# ---------------------------------------------------------------------------
# Constants + logging
# ---------------------------------------------------------------------------
CONF_DIR=/etc/ledgerline
CONF_FILE="${CONF_DIR}/deploy.conf"
PROVISIONED_MARKER="${CONF_DIR}/.provisioned"
LOG_DIR=/var/log/ledgerline
DEBIAN_CODENAME_EXPECTED=trixie
KEEP_RELEASES=5

TS="$(date +%Y%m%d-%H%M%S)"
LOG_FILE="${LOG_DIR}/$(basename "$0" .sh)-${TS}.log"

if [[ -t 1 ]]; then
    C_RESET=$'\033[0m'; C_BLUE=$'\033[1;34m'; C_GREEN=$'\033[1;32m'
    C_YELLOW=$'\033[1;33m'; C_RED=$'\033[1;31m'; C_DIM=$'\033[2m'
else
    C_RESET=; C_BLUE=; C_GREEN=; C_YELLOW=; C_RED=; C_DIM=
fi

_ts() { date '+%Y-%m-%d %H:%M:%S'; }
log()      { printf '%s %s\n' "$(_ts)" "$*" | tee -a "$LOG_FILE" >&2; }
log_step() { printf '\n%s%s ===== %s =====%s\n' "$C_BLUE" "$(_ts)" "$*" "$C_RESET" | tee -a "$LOG_FILE" >&2; }
log_info() { printf '%s%s  · %s%s\n' "$C_DIM" "$(_ts)" "$*" "$C_RESET" | tee -a "$LOG_FILE" >&2; }
log_ok()   { printf '%s%s  ✓ %s%s\n' "$C_GREEN" "$(_ts)" "$*" "$C_RESET" | tee -a "$LOG_FILE" >&2; }
log_warn() { printf '%s%s  ! %s%s\n' "$C_YELLOW" "$(_ts)" "$*" "$C_RESET" | tee -a "$LOG_FILE" >&2; }
log_err()  { printf '%s%s  ✗ %s%s\n' "$C_RED" "$(_ts)" "$*" "$C_RESET" | tee -a "$LOG_FILE" >&2; }

CURRENT_STEP="startup"
on_error() {
    local ec=$?
    log_err "FAILED during: ${CURRENT_STEP} (exit ${ec})"
    log_err "Log: ${LOG_FILE}"
    log_err "Re-run just this step after fixing:  sudo $0 step ${CURRENT_STEP}"
    exit "$ec"
}
trap on_error ERR

# Run a step function with a header + failure guidance.
run_step() {
    local fn="$1"; CURRENT_STEP="${fn#step_}"
    log_step "step: ${CURRENT_STEP}"
    "$fn"
    log_ok "step done: ${CURRENT_STEP}"
}

# ---------------------------------------------------------------------------
# Small helpers
# ---------------------------------------------------------------------------
have()          { command -v "$1" >/dev/null 2>&1; }
pkg_installed() { dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q 'install ok installed'; }
apt_candidate() { apt-cache policy "$1" 2>/dev/null | awk '/Candidate:/{print $2}'; }
apt_available() { local c; c="$(apt_candidate "$1")"; [[ -n "$c" && "$c" != "(none)" ]]; }

APT_UPDATED=0
apt_refresh() { if [[ "$APT_UPDATED" == 0 ]]; then DEBIAN_FRONTEND=noninteractive apt-get update -y >>"$LOG_FILE" 2>&1; APT_UPDATED=1; fi; }

# Install only the packages that are missing (idempotent, quiet-to-log).
apt_ensure() {
    local want=() p
    for p in "$@"; do pkg_installed "$p" || want+=("$p"); done
    if [[ ${#want[@]} -eq 0 ]]; then log_info "packages already present: $*"; return 0; fi
    apt_refresh
    log_info "apt install: ${want[*]}"
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends "${want[@]}" >>"$LOG_FILE" 2>&1
}

ask() { # ask VAR "Prompt" "default"
    local __var="$1" __prompt="$2" __default="${3:-}" __cur __ans
    __cur="${!__var:-}"
    if [[ -n "$__cur" ]]; then printf -v "$__var" '%s' "$__cur"; return; fi   # preseeded via env/conf
    if [[ "$ASSUME_YES" == 1 ]]; then printf -v "$__var" '%s' "$__default"; return; fi
    read -r -p "${__prompt}${__default:+ [$__default]}: " __ans </dev/tty || true
    printf -v "$__var" '%s' "${__ans:-$__default}"
}

rand_secret() { head -c 32 /dev/urandom | base64 | tr -dc 'A-Za-z0-9' | head -c 32; }

require_root() { [[ "$(id -u)" -eq 0 ]] || { log_err "Run as root (sudo)."; exit 1; }; }

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------
ASSUME_YES="${ASSUME_YES:-0}"

load_conf() { [[ -f "$CONF_FILE" ]] && { log_info "loading ${CONF_FILE}"; . "$CONF_FILE"; }; return 0; }

save_conf() {
    mkdir -p "$CONF_DIR"; chmod 700 "$CONF_DIR"
    umask 077
    cat >"$CONF_FILE" <<EOF
# Ledgerline deploy config — generated $(date). Safe to edit.
DOMAIN='${DOMAIN}'
APP_ROOT='${APP_ROOT}'
APP_USER='${APP_USER}'
REPO_URL='${REPO_URL}'
PHP_VERSION='${PHP_VERSION}'
DB_NAME='${DB_NAME}'
DB_USER='${DB_USER}'
DB_PASS='${DB_PASS}'
ACME_EMAIL='${ACME_EMAIL}'
FILESYSTEM_DISK='${FILESYSTEM_DISK}'
FILES_DISK='${FILES_DISK}'
EOF
    chmod 600 "$CONF_FILE"
    log_ok "config saved to ${CONF_FILE}"
}

gather_config() {
    load_conf
    ask DOMAIN     "Domain (FQDN served by Caddy)"        "${DOMAIN:-erp.example.com}"
    ask ACME_EMAIL "Email for Let's Encrypt/ACME"          "${ACME_EMAIL:-admin@${DOMAIN}}"
    ask APP_ROOT   "Install/storage location (app root)"   "${APP_ROOT:-/var/www/ledgerline}"
    ask APP_USER   "System user to run the app"            "${APP_USER:-ledgerline}"
    ask REPO_URL   "Git repository URL"                    "${REPO_URL:-git@github.com:MalteKiefer/Ledgerline.git}"
    ask DB_NAME    "PostgreSQL database name"              "${DB_NAME:-ledgerline}"
    ask DB_USER    "PostgreSQL user"                       "${DB_USER:-ledgerline}"
    DB_PASS="${DB_PASS:-}"; [[ -n "$DB_PASS" ]] || DB_PASS="$(rand_secret)"
    # File storage: local disk or S3-compatible (MinIO). Files module uses the
    # "files" disk; default it to local for a self-contained server.
    ask FILES_DISK "Files storage driver (local|s3)"       "${FILES_DISK:-local}"
    FILESYSTEM_DISK="${FILESYSTEM_DISK:-local}"
    PHP_VERSION="${PHP_VERSION:-$(detect_php_version)}"
    save_conf
}

detect_php_version() {
    # Prefer the Debian default (trixie = 8.4); fall back to whatever php-cli is.
    if apt_available php8.4-cli; then echo 8.4
    elif apt_available php8.3-cli; then echo 8.3
    else apt-cache search --names-only '^php[0-9.]+-cli$' | grep -oE 'php[0-9]+\.[0-9]+' | sort -V | tail -1 | sed 's/php//'; fi
}

php_pkgs() {
    local v="$1"
    echo "php${v}-fpm php${v}-cli php${v}-pgsql php${v}-mbstring php${v}-xml php${v}-curl php${v}-zip php${v}-gd php${v}-intl php${v}-bcmath php${v}-opcache php${v}-readline"
}

# ---------------------------------------------------------------------------
# Paths derived from APP_ROOT
# ---------------------------------------------------------------------------
paths() {
    RELEASES_DIR="${APP_ROOT}/releases"
    SHARED_DIR="${APP_ROOT}/shared"
    CURRENT_LINK="${APP_ROOT}/current"
    REPO_CACHE="${APP_ROOT}/repo.git"
    ENV_FILE="${SHARED_DIR}/.env"
    PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm-ledgerline.sock"
}

# ---------------------------------------------------------------------------
# Provisioning steps
# ---------------------------------------------------------------------------
step_preflight() {
    require_root
    mkdir -p "$LOG_DIR"; chmod 750 "$LOG_DIR"
    local codename; codename="$(. /etc/os-release; echo "${VERSION_CODENAME:-}")"
    if [[ "$codename" != "$DEBIAN_CODENAME_EXPECTED" ]]; then
        log_warn "Expected Debian ${DEBIAN_CODENAME_EXPECTED}, found '${codename}'. Continuing, but package names may differ."
    else
        log_ok "Debian ${codename} detected"
    fi
    apt_ensure ca-certificates curl gnupg git rsync acl unzip apt-transport-https lsb-release
}

step_repos() {
    # Caddy — the Debian package lags; use the official repo for the current
    # release + reliable ACME. Only added if missing.
    if [[ ! -f /etc/apt/sources.list.d/caddy-stable.list ]]; then
        log_info "adding official Caddy apt repo"
        curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
            | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
        curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
            | tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
        APT_UPDATED=0
    else
        log_info "Caddy repo already present"
    fi

    # Node: prefer Debian's if >= 20, else NodeSource 22 LTS.
    local deb_node; deb_node="$(apt_candidate nodejs || true)"
    if [[ "$deb_node" =~ ^([0-9]+) ]] && (( ${BASH_REMATCH[1]} >= 20 )); then
        log_info "Debian ships nodejs ${deb_node} (>=20) — using it"
    elif [[ ! -f /etc/apt/sources.list.d/nodesource.list ]]; then
        log_info "adding NodeSource (Node 22 LTS) — Debian node too old/absent"
        curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
            | gpg --dearmor -o /usr/share/keyrings/nodesource.gpg
        echo "deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main" \
            > /etc/apt/sources.list.d/nodesource.list
        APT_UPDATED=0
    fi
}

step_packages() {
    # PHP + extensions (predis is pure-PHP, so no php-redis needed).
    # shellcheck disable=SC2046
    apt_ensure $(php_pkgs "$PHP_VERSION")
    # sodium (backup archive crypto) is usually built into Debian's PHP; install
    # the split package if it exists, otherwise verify it's compiled in.
    if apt_available "php${PHP_VERSION}-sodium"; then apt_ensure "php${PHP_VERSION}-sodium"; fi
    # Imagick + HEIC decode: imagemagick with the heif delegate + libheif tools.
    if apt_available "php${PHP_VERSION}-imagick"; then apt_ensure "php${PHP_VERSION}-imagick"
    elif apt_available php-imagick; then apt_ensure php-imagick
    else log_warn "php-imagick not in apt — HEIC via Imagick may be unavailable"; fi
    apt_ensure imagemagick libheif1 libheif-examples ffmpeg

    # Node/npm, PostgreSQL, Caddy.
    apt_ensure nodejs
    have npm || apt_ensure npm
    apt_ensure postgresql postgresql-contrib
    apt_ensure caddy

    # Valkey (Redis-compatible). Debian trixie packages it; fall back to redis.
    if apt_available valkey-server; then apt_ensure valkey-server
    elif apt_available redis-server; then log_warn "valkey-server not in apt — using redis-server (compatible)"; apt_ensure redis-server
    else log_err "Neither valkey-server nor redis-server available"; return 1; fi

    # Composer (official installer, pinned-hash verified).
    if ! have composer; then
        log_info "installing Composer"
        local exp got tmp; tmp="$(mktemp -d)"
        exp="$(curl -fsSL https://composer.github.io/installer.sig)"
        curl -fsSL https://getcomposer.org/installer -o "$tmp/composer-setup.php"
        got="$(php -r "echo hash_file('sha384','$tmp/composer-setup.php');")"
        [[ "$exp" == "$got" ]] || { log_err "Composer installer checksum mismatch"; rm -rf "$tmp"; return 1; }
        php "$tmp/composer-setup.php" --install-dir=/usr/local/bin --filename=composer >>"$LOG_FILE" 2>&1
        rm -rf "$tmp"
    else
        log_info "Composer already installed"
    fi
    log_ok "php $(php -r 'echo PHP_VERSION;'), node $(node -v), composer $(composer --version 2>/dev/null | awk '{print $3}')"
}

step_user() {
    if ! id -u "$APP_USER" >/dev/null 2>&1; then
        log_info "creating system user ${APP_USER}"
        useradd --system --create-home --home-dir "/home/${APP_USER}" --shell /usr/sbin/nologin "$APP_USER"
    fi
    paths
    mkdir -p "$RELEASES_DIR" "$SHARED_DIR/storage" "$(dirname "$REPO_CACHE")"
    # Laravel's storage subtree lives in shared/ and is symlinked into releases.
    mkdir -p "$SHARED_DIR"/storage/{app/public,framework/{cache/data,sessions,views},logs}
    mkdir -p "$SHARED_DIR/storage/app/private"
    chown -R "$APP_USER:$APP_USER" "$APP_ROOT"
    # Caddy (www-data) must traverse to public/; give it group access.
    usermod -aG "$APP_USER" caddy 2>/dev/null || true
    log_ok "app root ready at ${APP_ROOT}"
}

step_php() {
    paths
    # Dedicated FPM pool running as the app user over a private socket.
    local pool="/etc/php/${PHP_VERSION}/fpm/pool.d/ledgerline.conf"
    cat >"$pool" <<EOF
[ledgerline]
user = ${APP_USER}
group = ${APP_USER}
listen = ${PHP_FPM_SOCK}
listen.owner = caddy
listen.group = caddy
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
php_admin_value[upload_max_filesize] = 550M
php_admin_value[post_max_size] = 560M
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 120
catch_workers_output = yes
EOF
    # Production opcache.
    local ini="/etc/php/${PHP_VERSION}/fpm/conf.d/99-ledgerline.ini"
    cat >"$ini" <<'EOF'
opcache.enable=1
opcache.memory_consumption=192
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.interned_strings_buffer=16
realpath_cache_size=4096K
realpath_cache_ttl=600
EOF
    # Verify sodium is available (backup crypto hard-depends on it).
    php -m | grep -qi '^sodium$' || log_warn "PHP ext-sodium missing — backup archive encryption will fail!"
    systemctl enable --now "php${PHP_VERSION}-fpm" >>"$LOG_FILE" 2>&1
    systemctl restart "php${PHP_VERSION}-fpm"
    log_ok "php-fpm pool 'ledgerline' active on ${PHP_FPM_SOCK}"
}

step_postgres() {
    systemctl enable --now postgresql >>"$LOG_FILE" 2>&1
    # Idempotent role + database creation.
    if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1; then
        log_info "creating role ${DB_USER}"
        sudo -u postgres psql -c "CREATE ROLE \"${DB_USER}\" LOGIN PASSWORD '${DB_PASS}';" >>"$LOG_FILE" 2>&1
    else
        log_info "role ${DB_USER} exists — syncing password"
        sudo -u postgres psql -c "ALTER ROLE \"${DB_USER}\" WITH PASSWORD '${DB_PASS}';" >>"$LOG_FILE" 2>&1
    fi
    if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1; then
        log_info "creating database ${DB_NAME}"
        sudo -u postgres psql -c "CREATE DATABASE \"${DB_NAME}\" OWNER \"${DB_USER}\";" >>"$LOG_FILE" 2>&1
    fi
    log_ok "postgres: db ${DB_NAME} owned by ${DB_USER}"
}

step_valkey() {
    local svc=valkey-server
    systemctl list-unit-files | grep -q '^valkey-server' || svc=redis-server
    systemctl enable --now "$svc" >>"$LOG_FILE" 2>&1
    log_ok "${svc} running (Redis protocol on 127.0.0.1:6379)"
}

step_caddy() {
    paths
    local cf=/etc/caddy/Caddyfile
    cat >"$cf" <<EOF
# Managed by scripts/server.sh — Ledgerline
${DOMAIN} {
    root * ${CURRENT_LINK}/public
    encode zstd gzip
    php_fastcgi unix/${PHP_FPM_SOCK}
    file_server
    header -Server
    request_body {
        max_size 550MB
    }
    tls ${ACME_EMAIL}
    log {
        output file ${LOG_DIR}/caddy-access.log
    }
}
EOF
    caddy validate --config "$cf" >>"$LOG_FILE" 2>&1 || { log_err "Caddyfile invalid"; return 1; }
    systemctl enable --now caddy >>"$LOG_FILE" 2>&1
    systemctl reload caddy 2>>"$LOG_FILE" || systemctl restart caddy
    log_ok "Caddy serving ${DOMAIN} → ${CURRENT_LINK}/public"
}

step_services() {
    paths
    # Queue worker.
    cat >/etc/systemd/system/ledgerline-worker.service <<EOF
[Unit]
Description=Ledgerline queue worker
After=network.target postgresql.service
[Service]
User=${APP_USER}
Group=${APP_USER}
Restart=always
RestartSec=3
WorkingDirectory=${CURRENT_LINK}
ExecStart=/usr/bin/php ${CURRENT_LINK}/artisan queue:work --sleep=1 --tries=3 --max-time=3600 --timeout=600
[Install]
WantedBy=multi-user.target
EOF
    # Scheduler (schedule:work runs due tasks each minute; no cron needed).
    cat >/etc/systemd/system/ledgerline-scheduler.service <<EOF
[Unit]
Description=Ledgerline scheduler
After=network.target postgresql.service
[Service]
User=${APP_USER}
Group=${APP_USER}
Restart=always
RestartSec=5
WorkingDirectory=${CURRENT_LINK}
ExecStart=/usr/bin/php ${CURRENT_LINK}/artisan schedule:work
[Install]
WantedBy=multi-user.target
EOF
    systemctl daemon-reload
    systemctl enable ledgerline-worker ledgerline-scheduler >>"$LOG_FILE" 2>&1
    log_ok "systemd units installed (started on first deploy)"
}

# ---------------------------------------------------------------------------
# .env — created once in shared/, reused by every release
# ---------------------------------------------------------------------------
ensure_env() {
    paths
    if [[ -f "$ENV_FILE" ]]; then log_info ".env already present (${ENV_FILE})"; return; fi
    log_info "generating ${ENV_FILE}"
    umask 077
    cat >"$ENV_FILE" <<EOF
APP_NAME=Ledgerline
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://${DOMAIN}

APP_LOCALE=en
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=127.0.0.1
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

FILESYSTEM_DISK=${FILESYSTEM_DISK}
FILES_DISK=${FILES_DISK}

BROADCAST_CONNECTION=log
MAIL_MAILER=log
EOF
    if [[ "$FILES_DISK" == "s3" ]]; then
        cat >>"$ENV_FILE" <<'EOF'

# S3/MinIO for the Files module — fill these in:
FILES_S3_KEY=
FILES_S3_SECRET=
FILES_S3_REGION=us-east-1
FILES_S3_BUCKET=ledgerline-files
FILES_S3_ENDPOINT=
FILES_S3_USE_PATH_STYLE=true
EOF
        log_warn "FILES_DISK=s3 — fill the FILES_S3_* values in ${ENV_FILE} before serving Files"
    fi
    chown "$APP_USER:$APP_USER" "$ENV_FILE"; chmod 600 "$ENV_FILE"
}

# ---------------------------------------------------------------------------
# Deploy — atomic release with symlink swap
# ---------------------------------------------------------------------------
sync_repo_cache() {
    paths
    if [[ ! -d "$REPO_CACHE" ]]; then
        if [[ "$REPO_URL" == git@* || "$REPO_URL" == ssh://* ]]; then
            log_info "private repo over SSH — ${APP_USER} needs a deploy key at /home/${APP_USER}/.ssh/id_ed25519 with read access to ${REPO_URL}"
        fi
        log_info "cloning ${REPO_URL} (mirror cache)"
        sudo -u "$APP_USER" git clone --mirror "$REPO_URL" "$REPO_CACHE" >>"$LOG_FILE" 2>&1
    else
        log_info "fetching latest refs"
        sudo -u "$APP_USER" git -C "$REPO_CACHE" remote update --prune >>"$LOG_FILE" 2>&1
    fi
}

latest_tag() { sudo -u "$APP_USER" git -C "$REPO_CACHE" tag --sort=-v:refname | head -1; }

step_deploy() {
    require_root; paths
    ensure_env
    sync_repo_cache

    # Pick the ref: arg > env DEPLOY_REF > prompt (default = latest tag).
    local ref="${DEPLOY_REF:-}"
    if [[ -z "$ref" ]]; then
        local def; def="$(latest_tag)"
        log_info "available tags (newest first):"
        sudo -u "$APP_USER" git -C "$REPO_CACHE" tag --sort=-v:refname | head -8 | sed 's/^/      /' | tee -a "$LOG_FILE" >&2
        ask ref "Version to deploy (tag or branch)" "$def"
    fi
    [[ -n "$ref" ]] || { log_err "No ref given"; return 1; }
    sudo -u "$APP_USER" git -C "$REPO_CACHE" rev-parse --verify "${ref}^{commit}" >/dev/null 2>&1 \
        || { log_err "ref '${ref}' not found in repo"; return 1; }

    local rel="${RELEASES_DIR}/${TS}-${ref//\//_}"
    log_step "deploy: ${ref} → ${rel}"

    log_info "checking out worktree"
    sudo -u "$APP_USER" git clone --quiet --shared "$REPO_CACHE" "$rel" >>"$LOG_FILE" 2>&1
    sudo -u "$APP_USER" git -C "$rel" checkout --quiet --detach "$ref" >>"$LOG_FILE" 2>&1

    log_info "linking shared .env + storage"
    sudo -u "$APP_USER" ln -sfn "$ENV_FILE" "$rel/.env"
    rm -rf "$rel/storage"
    sudo -u "$APP_USER" ln -sfn "$SHARED_DIR/storage" "$rel/storage"

    log_info "composer install (no-dev, optimized)"
    sudo -u "$APP_USER" -H composer install --working-dir="$rel" \
        --no-dev --optimize-autoloader --no-interaction --no-progress >>"$LOG_FILE" 2>&1

    log_info "npm ci + build"
    sudo -u "$APP_USER" -H bash -lc "cd '$rel' && npm ci --no-audit --no-fund && npm run build" >>"$LOG_FILE" 2>&1

    # First deploy: generate the app key into shared .env.
    if ! grep -qE '^APP_KEY=base64:' "$ENV_FILE"; then
        log_info "generating APP_KEY"
        sudo -u "$APP_USER" php "$rel/artisan" key:generate --force >>"$LOG_FILE" 2>&1
    fi

    log_info "storage:link + migrate"
    sudo -u "$APP_USER" php "$rel/artisan" storage:link >>"$LOG_FILE" 2>&1 || true
    sudo -u "$APP_USER" php "$rel/artisan" migrate --force >>"$LOG_FILE" 2>&1

    log_info "caching config/routes/views/events"
    sudo -u "$APP_USER" php "$rel/artisan" optimize:clear >>"$LOG_FILE" 2>&1
    sudo -u "$APP_USER" php "$rel/artisan" optimize >>"$LOG_FILE" 2>&1

    # Atomic swap.
    log_info "activating release (symlink swap)"
    ln -sfn "$rel" "${CURRENT_LINK}.tmp" && mv -Tf "${CURRENT_LINK}.tmp" "$CURRENT_LINK"

    log_info "restarting services"
    systemctl reload "php${PHP_VERSION}-fpm" 2>>"$LOG_FILE" || systemctl restart "php${PHP_VERSION}-fpm"
    systemctl restart ledgerline-worker ledgerline-scheduler
    systemctl reload caddy 2>>"$LOG_FILE" || systemctl restart caddy

    prune_releases
    touch "$PROVISIONED_MARKER"
    log_ok "deployed ${ref} — live at https://${DOMAIN}"
}

prune_releases() {
    paths
    local keep="$KEEP_RELEASES" old
    mapfile -t old < <(ls -1dt "${RELEASES_DIR}"/*/ 2>/dev/null | tail -n +$((keep + 1)))
    for old in "${old[@]:-}"; do [[ -n "$old" ]] || continue; log_info "pruning old release $(basename "$old")"; rm -rf "$old"; done
}

step_rollback() {
    require_root; paths
    local prev; prev="$(ls -1dt "${RELEASES_DIR}"/*/ 2>/dev/null | sed -n '2p')"
    [[ -n "$prev" ]] || { log_err "No previous release to roll back to"; return 1; }
    prev="${prev%/}"
    log_warn "rolling back to $(basename "$prev")"
    ln -sfn "$prev" "${CURRENT_LINK}.tmp" && mv -Tf "${CURRENT_LINK}.tmp" "$CURRENT_LINK"
    systemctl reload "php${PHP_VERSION}-fpm" || true
    systemctl restart ledgerline-worker ledgerline-scheduler
    systemctl reload caddy || true
    log_ok "rolled back to $(basename "$prev")"
}

# ---------------------------------------------------------------------------
# Doctor
# ---------------------------------------------------------------------------
step_doctor() {
    paths
    local ok=1
    check() { if eval "$2" >/dev/null 2>&1; then log_ok "$1"; else log_err "$1"; ok=0; fi; }
    check "php ${PHP_VERSION} cli"        "php -v"
    check "php ext: pgsql"                "php -m | grep -qi '^pdo_pgsql$'"
    check "php ext: sodium (backups)"     "php -m | grep -qi '^sodium$'"
    check "php ext: gd"                   "php -m | grep -qi '^gd$'"
    check "php ext: imagick (HEIC)"       "php -m | grep -qi '^imagick$'"
    check "php ext: intl"                 "php -m | grep -qi '^intl$'"
    check "php ext: mbstring"             "php -m | grep -qi '^mbstring$'"
    check "composer"                      "have composer"
    check "node >= 20"                    "node -v | grep -qE 'v(2[0-9]|[3-9][0-9])'"
    check "ffmpeg"                        "have ffmpeg"
    check "HEIC decode (ImageMagick)"     "convert -list format 2>/dev/null | grep -qi heic"
    check "postgresql running"            "systemctl is-active --quiet postgresql"
    check "valkey/redis on 6379"          "(have valkey-cli && valkey-cli ping | grep -q PONG) || (have redis-cli && redis-cli ping | grep -q PONG)"
    check "php-fpm running"               "systemctl is-active --quiet php${PHP_VERSION}-fpm"
    check "caddy running"                 "systemctl is-active --quiet caddy"
    check "app deployed (current)"        "[[ -L '${CURRENT_LINK}' ]]"
    [[ "$ok" == 1 ]] && log_ok "doctor: all good" || { log_err "doctor: issues above"; return 1; }
}

# ---------------------------------------------------------------------------
# Orchestration
# ---------------------------------------------------------------------------
PROVISION_STEPS=(preflight repos packages user php postgres valkey caddy services)

do_provision() {
    require_root; gather_config; paths
    local s; for s in "${PROVISION_STEPS[@]}"; do run_step "step_${s}"; done
    log_ok "provisioning complete"
}

is_provisioned() { [[ -f "$PROVISIONED_MARKER" ]]; }

usage() {
    grep -E '^#( |$)' "$0" | sed -E 's/^# ?//' | sed -n '1,30p'
    echo; echo "Steps: ${PROVISION_STEPS[*]}"
}

main() {
    mkdir -p "$LOG_DIR" 2>/dev/null || true
    local cmd="${1:-auto}"; shift || true
    case "$cmd" in
        provision) do_provision ;;
        deploy)    require_root; gather_config; [[ $# -ge 1 ]] && DEPLOY_REF="$1"; run_step step_deploy ;;
        rollback)  gather_config; run_step step_rollback ;;
        doctor)    gather_config; run_step step_doctor ;;
        steps)     echo "${PROVISION_STEPS[*]} deploy rollback doctor" ;;
        step)      require_root; gather_config; paths
                   local name="${1:?step name required}"
                   declare -F "step_${name}" >/dev/null || { log_err "unknown step: ${name}"; exit 1; }
                   run_step "step_${name}" ;;
        auto)
            require_root; gather_config
            if is_provisioned && step_doctor >/dev/null 2>&1; then
                log_info "environment already provisioned — deploying only"
            else
                do_provision
            fi
            run_step step_deploy ;;
        -h|--help|help) usage ;;
        *) log_err "unknown command: ${cmd}"; usage; exit 1 ;;
    esac
    log_ok "finished. Full log: ${LOG_FILE}"
}

main "$@"
