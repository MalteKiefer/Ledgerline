# Ledgerline

Ledgerline is a **self-hosted, plaintext-relational personal cloud**. Photos,
files, notes, todos, bookmarks, invoices, health data and GPS tracks live on your
own server as ordinary relational rows — one Eloquent table per module — so
everything is queryable, joinable, searchable and fast, and the server renders
your data directly.

Ledgerline is **not** zero-knowledge (it was, historically — that architecture
was deliberately retired). User content is stored **in the clear at rest**;
confidentiality at rest is an infrastructure concern you own (run on a
**LUKS full-disk-encrypted** host and take **encrypted backups**). What the app
guarantees instead is a hardened application and transport layer: **TLS 1.3 +
HSTS**, **mandatory e-mail + password + TOTP two-factor** (Laravel Fortify),
per-user owner-scoping and policies on every controller, a strict CSP, SSRF-guarded
outbound calls, and rate limits. **Operational secrets** — SMTP credentials,
backup-destination credentials, the backup passphrase, Paperless tokens — are the
exception: they are still Laravel-`encrypted` at rest (AES-256-GCM under `APP_KEY`,
which is never part of a database dump).

Authentication is **first-party** (Laravel Fortify): e-mail + password with
optional TOTP two-factor. All assets are bundled and served locally — no external
CDNs, fonts, or trackers.

> **New to the codebase?** `CLAUDE.md` is the working context + security decision
> log. This README is the maintained feature + security description. The
> machine-readable API contract is [`openapi.yaml`](openapi.yaml).

---

## Contents

- [Modules](#modules)
- [Stack](#stack)
- [Requirements](#requirements)
- [Installation](#installation-docker-compose)
- [Reverse proxy configuration](#reverse-proxy-configuration)
- [Configuration reference](#configuration-reference-environment-variables)
- [**Security — the full breakdown**](#security--the-full-breakdown)
  - [Threat model: what this design protects and what it does not](#threat-model-what-this-design-protects-and-what-it-does-not)
  - [Authentication & two-factor](#authentication--two-factor)
  - [Authorization & tenant isolation](#authorization--tenant-isolation)
  - [Encrypted operational secrets](#encrypted-operational-secrets)
  - [Device tokens (mobile / CLI)](#device-tokens-mobile--cli)
  - [Transport, headers, CSP](#transport-headers-csp)
  - [Upload handling & untrusted content](#upload-handling--untrusted-content)
  - [SSRF-guarded outbound calls](#ssrf-guarded-outbound-calls)
  - [Rate limiting](#rate-limiting)
  - [Audit logging](#audit-logging)
  - [Backups](#backups)
  - [Deletion](#deletion)
  - [Supply chain & static analysis](#supply-chain--static-analysis)
- [API](#api)
- [Development workflow](#development-workflow)
- [License](#license)

---

## Modules

Every module is a set of relational tables exposed over per-record REST (with a
soft-delete trash and optimistic `version` + 409 conflict handling). Pages use
**hybrid rendering**: the server renders the Blade shell and inlines the initial
data via `@js()`; all mutations go through JSON endpoints shared by the web
session and the `/api/v1` device API.

- **Dashboard** — a start page aggregating widgets (upcoming todos, health values
  + quick-add, an "on this day" photo strip, storage usage, counters, recent
  notes, an active-fast banner). Reads directly from the module endpoints.
- **Gallery** — photos & videos with albums, a map view, and a timeline. Uploads
  are stored as plaintext binaries on disk; the server generates thumbnail /
  medium / motion renditions and extracts EXIF, perceptual hash, dimensions,
  taken-at, GPS and camera server-side, so timeline/map/stats query directly.
  Renditions are served by id through sandboxed routes. **CLIP semantic search +
  face recognition are being re-added** (the ML services remain; they are
  returning behind the immich-ml sidecar).
- **Files** — a nestable folder browser with whole + chunked upload, versioning,
  per-user quota, and streamed download. Files are stored as plaintext binaries
  on disk; previews render from the raw URL.
- **Notes / Todos / Bookmarks** — relational records rendered client-side (Notes
  Markdown via marked + DOMPurify).
- **Finance** — a finance hub. **Invoices** with print/PDF templates, **server-
  authoritative GoBD-safe numbering** (atomic per-year sequence on finalize), and
  ZUGFeRD/Factur-X (EN 16931) XML export + e-invoice / PDF import. **Payment
  methods** (bank accounts / cards / PayPal). **Bank-statement import** (MT940 +
  CSV with a column-mapping step, parsed client-side, server-side signature dedup)
  with auto-matching of incoming payments to invoices. **Receipts** — drag-and-drop
  upload, client-side OCR + heuristic pattern recognition (merchant, total, date,
  invoice number, category, VAT rate) with a **server-side OCR fallback**
  (`/api/v1/invoices/ocr`, transient), content-hash duplicate detection, a
  per-merchant category-learning helper, inline document preview, and a ZIP export
  for the accountant. **Business partners**, **cost projects**, **receipt
  categories**, **statistics** + a **VAT return** overview.
- **Explore** — a map-centric view unifying gallery photo pins with self-recorded
  / imported **GPS tracks** (GPX/KML/KMZ/TCX/FIT, parsed client-side) and
  automatic photo-to-track coupling. The map renders with Leaflet + OpenStreetMap
  raster tiles loaded directly in the browser. Optional opt-in tour auto-routing
  (self-hosted GraphHopper or public OSRM).
- **Health** — health tracking (weight, blood pressure, pulse, SpO₂, temperature,
  glucose) plus **intermittent fasting**, charts (uPlot), and a doctor-export.
- **Backup** — encrypted, incremental backups to S3/B2/SFTP/WebDAV.
- **Paperless** — per-user Paperless-ngx integration.

> **Deprecated:** the Chromium browser extension in `extension/` was built for the
> old zero-knowledge password manager and is **orphaned** by the pivot (it targets
> routes that no longer exist). It is slated for removal and is not part of the
> served application.

---

## Stack

| Component     | Version        | Notes                                                   |
| ------------- | -------------- | ------------------------------------------------------- |
| PHP           | 8.5            | `declare(strict_types=1)`, full type hints, PHPStan L10 |
| Laravel       | 13.x           | Framework                                               |
| PostgreSQL    | 18 (pgvector)  | `vector` extension for CLIP/face similarity (returning) |
| Valkey        | 8.x            | Cache, session, queue (Redis-compatible, `predis`)      |
| Node.js       | 24 LTS / Vite 8 / Tailwind 4 | Asset build                               |
| Alpine.js     | 3.x (modular)  | `resources/js/app.js` + `shared/*` + `components/*`     |
| Laravel Fortify | 1.x          | First-party auth (email+password, TOTP 2FA, reset/verify) |
| Laravel Sanctum | 4.x          | Bearer tokens for the mobile / CLI `/api/v1`            |
| sabre/dav     | 4.x            | WebDAV (files-over-WebDAV + a backup destination)       |
| immich-machine-learning | optional | Face detection + CLIP embeddings (profile-gated, returning) |

Client-side runtime libraries (bundled locally, no CDN): **pdfjs-dist** +
**tesseract.js** (client PDF/receipt text), **leaflet** (+ markercluster),
**uplot** (charts), **html2canvas** + **jspdf** (invoice/receipt PDF rendering),
**fflate** (KMZ unzip + receipt ZIP export), **marked** + **dompurify** (Notes
Markdown), **codemirror**, **qrcode**.

---

## Requirements

Ledgerline is designed to run as a Docker Compose stack. To operate it you need:

| Requirement | Version / notes |
| --- | --- |
| **Docker + Docker Compose** | Compose v2 (the `docker compose` plugin). The production image is built locally from the repo `Dockerfile`. |
| **PostgreSQL 18 with pgvector** | Provided by the bundled `db` service (`pgvector/pgvector:pg18`). The `vector` extension backs CLIP / face-similarity duplicate detection (returning). |
| **Valkey 8** | Provided by the bundled `valkey` service. Redis-protocol compatible; used for cache, session and queue via the pure-PHP `predis` client (no `phpredis` extension needed). |
| **(No external identity provider)** | Authentication is first-party (Laravel Fortify): e-mail + password with optional TOTP 2FA. The first user is bootstrapped with `php artisan user:set-password` (mail-independent). An SMTP server is optional (configured in-app; used for password reset / verification / invite links) — without it, `user:set-password` and admin-generated invite links are the account-provisioning paths. |
| **Object storage (optional but recommended)** | An S3-compatible bucket (Amazon S3, Cloudflare R2, Backblaze B2, Hetzner Object Storage, MinIO, …) for the private `files` blob disk. If omitted, blobs are stored on the local `app-storage` volume. |
| **A TLS-terminating reverse proxy** | Production expects **Caddy on the host** (or any equivalent) in front of the app, which binds to `127.0.0.1:${APP_PORT}` only. The proxy terminates TLS 1.3 and forwards `X-Forwarded-*`. Secure cookies + HSTS are emitted when `SESSION_SECURE_COOKIE=true`. |
| **A LUKS full-disk-encrypted host (recommended)** | User content is stored in the clear at rest, so confidentiality against disk/backup theft is an infra concern. Run on encrypted storage and take encrypted backups. |
| **Node.js 24 LTS** | Only for local (non-Docker) development / building assets. In the Docker build, assets are compiled in a Node stage automatically. |

Optional, profile-gated sidecars (all self-hosted, all off by default):

| Service | Compose profile | Purpose |
| --- | --- | --- |
| `ml` (immich-machine-learning) | `ml` | CLIP object/scene tagging + smart search and facial recognition (returning). Reached over the internal network at `http://ml:3003`. |
| `photon` (Photon geocoder) | `geocode` | Self-hosted reverse geocoding so photo coordinates for the imported region never leave the host (falls back to public Nominatim on a miss). |
| `graphhopper` (GraphHopper router) | `maps` | Self-hosted Explore tour auto-routing with elevation + OSM surface data (alternative to the default public OSRM). |

All images are pinned by immutable digest; every service runs non-root with
`no-new-privileges` and `cap_drop: [ALL]` (selective capability re-add).

---

## Installation (Docker Compose)

### 1. Clone and prepare the environment file

```bash
git clone <this-repository> ledgerline && cd ledgerline
cp .env.docker.example .env
```

`.env` is read both by `docker compose` (for `${...}` interpolation) and by the
app containers (via `env_file`), so a single file configures the whole stack.
See [Configuration reference](#configuration-reference-environment-variables) for
every variable.

### 2. Generate the application key

```bash
docker compose run --rm app php artisan key:generate --show
```

Copy the printed `base64:…` value into `APP_KEY` in `.env`. This key encrypts
sessions and the `encrypted`-cast operational secrets (SMTP / backup / Paperless
credentials); it is never part of a database dump, so those secrets survive a
dump exfiltration. User content itself is stored in the clear.

### 3. Set the required secrets

At minimum, fill these in `.env`:

- `DB_PASSWORD` and `REDIS_PASSWORD` (both **required**) — datastore credentials.
  These are delivered to the `db`/`valkey` containers as mounted secret files, so
  they never appear in `docker inspect` or `/proc/<pid>/environ`.
- `APP_URL` — your public HTTPS URL (e.g. `https://cloud.example.com`).
- `TRUSTED_PROXIES` — the private range(s) your host proxy reaches the container
  over. **Never `*`** in production behind a shared network.

### 4. Create the first (admin) user

Authentication is first-party, so there is no external IdP to configure. After
the stack is up (step 6), create the initial admin account from the CLI — this
works with or without SMTP configured:

```bash
docker compose exec app php artisan user:set-password owner@example.com --admin
```

The lowest-id user is the workspace admin (`users.role = admin`, a non-fillable
privilege boundary); admins manage other users, per-user quota/device caps,
groups, per-module access, and workspace settings in the UI. Additional users are
created by an admin (temporary password or a copy-/mail-able invite link with a
chosen expiry). **Optional self-registration** is off by default
(`allow_registration`); when enabled, new users are always `role = user` and go
through e-mail verification. SMTP is configured **in-app** (Notifications
settings) and, when present, powers password reset / verification / invite mails;
the `MAIL_MAILER=log` default is fine for a mail-less install.

### 5. Choose your blob storage

- **Local (default):** leave `FILES_DISK=local` — uploads land in the
  `app-storage` volume. Simplest, no external dependency.
- **S3-compatible:** set `FILES_DISK=files` and fill the `FILES_S3_*` block
  (`FILES_S3_KEY`, `FILES_S3_SECRET`, `FILES_S3_BUCKET`, `FILES_S3_REGION`,
  `FILES_S3_ENDPOINT`, `FILES_S3_USE_PATH_STYLE`). Keep the bucket **private**; the
  app streams every byte behind auth (stored bytes are plaintext — protect the
  bucket accordingly).

### 6. Build and start the stack

```bash
docker compose build
docker compose up -d          # starts app + worker + scheduler + db + valkey
```

The `app` service runs database migrations and cache warm-up automatically on
start (isolated behind a lock so `worker`/`scheduler` skip it). No manual
`php artisan migrate` step is required. Core services:

- **`app`** — nginx + php-fpm, serves the UI and API on `:8080` (mapped to
  `127.0.0.1:${APP_PORT}`), runs migrations + `optimize` on boot.
- **`worker`** — `queue:work` for photo/video processing, backups, etc. Scale it:
  `docker compose up -d --scale worker=10`.
- **`scheduler`** — `schedule:work` (orphan sweeps, token pruning, backups).
- **`db`** — PostgreSQL 18 + pgvector.
- **`valkey`** — cache / session / queue.

### 7. (Optional) enable sidecars

```bash
docker compose --profile ml up -d        # ML sidecar (also set ML_ENABLED=true / FACE_ENABLED=true)
docker compose --profile geocode up -d   # Photon geocoder (also set PHOTON_URL=http://photon:2322)
docker compose --profile maps up -d      # GraphHopper router (see .env.docker.example for graph setup)
```

Enabling a profile only starts the container — you still flip the matching feature
flags in `.env` (e.g. `ML_ENABLED`, `PHOTON_URL`, `MAPS_ROUTE_ENGINE`) and
re-run `docker compose up -d` so the app picks them up. The ML and GraphHopper
sidecars download / import models or graphs on first use and can take minutes to
become healthy — watch with `docker compose logs -f <service>`.

### 8. Put a reverse proxy in front (TLS)

The app binds to `127.0.0.1:${APP_PORT}` (default `8300`). Put a TLS-terminating
reverse proxy on the host in front of it; keep `SESSION_SECURE_COOKIE=true` so
Secure cookies + HSTS are emitted. Copy-ready, hardened configs for **Caddy,
nginx, Apache, Traefik and lighttpd** are in
[Reverse proxy configuration](#reverse-proxy-configuration) below.

### 9. Verify

```bash
docker compose ps                              # every service healthy
curl -fsS https://cloud.example.com/up         # → 200
```

Then open `APP_URL` and sign in with the e-mail + password you set in step 4
(and enrol TOTP 2FA in your profile).

### Local development (without Docker)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# configure DB_*, REDIS_*, and the "files" S3 disk (MinIO locally)
php artisan migrate
php artisan user:set-password you@example.com --admin   # first user
npm run dev            # or: npm run build
php artisan serve
```

---

## Reverse proxy configuration

Ledgerline listens on `127.0.0.1:8300` (`APP_PORT`) with **plain HTTP** — a
TLS-terminating reverse proxy on the host is mandatory in production. All examples
below assume the app at `127.0.0.1:8300` and the domain `cloud.example.com`; adjust
both. They cover the proxy's only three jobs — **terminate TLS, forward the client
headers, allow the upload size** — and deliberately leave the security headers to
the app.

**Shared rules (all proxies):**

- **Forwarded headers.** Pass `Host`, `X-Forwarded-For` and `X-Forwarded-Proto` so
  the app builds correct URLs and logs the real client IP.
- **`TRUSTED_PROXIES`.** Set it in `.env` to the address the proxy connects *from*.
  On a Docker install the app sees the request arriving from the Docker bridge
  gateway, so use that private subnet (e.g. `172.16.0.0/12`), **never `*`** — a
  wildcard lets a remote client forge `X-Forwarded-For` and spoof its source IP.
- **Upload size.** The image allows a **560 MiB** body (`NGINX_CLIENT_MAX_BODY_SIZE=560M`,
  `PHP_POST_MAX_SIZE=550M`). Set the proxy's limit to match; raise all three together
  for larger uploads.
- **Don't re-add security headers.** The app emits CSP, HSTS, `X-Frame-Options`,
  `Referrer-Policy`, COOP and `Permissions-Policy` itself
  (`app/Http/Middleware/SecurityHeaders.php`). Duplicating them at the proxy can
  conflict — keep the proxy to TLS + forwarding + body size.
- **Verify** after a reload: `curl -fsS https://cloud.example.com/up` → `200`.

### Caddy (recommended — automatic TLS, minimal surface)

`/etc/caddy/Caddyfile`:

```caddy
cloud.example.com {
	# Match the app's 560 MiB upload limit; Caddy streams the body.
	request_body {
		max_size 560MB
	}

	reverse_proxy 127.0.0.1:8300 {
		# Caddy forwards Host + X-Forwarded-For automatically; make the scheme explicit.
		header_up X-Forwarded-Proto {scheme}
	}

	# TLS 1.3 only. Caddy provisions the Let's Encrypt cert and staples OCSP itself.
	tls {
		protocols tls1.3
	}

	# The app sets CSP/HSTS/etc.; just drop the Server banner.
	header -Server

	encode zstd gzip
}
```

Caddy auto-redirects HTTP→HTTPS and renews certificates. Reload: `caddy reload --config /etc/caddy/Caddyfile`.

### nginx

`/etc/nginx/conf.d/ledgerline.conf` (certs e.g. from certbot):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name cloud.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name cloud.example.com;

    ssl_certificate         /etc/letsencrypt/live/cloud.example.com/fullchain.pem;
    ssl_certificate_key     /etc/letsencrypt/live/cloud.example.com/privkey.pem;
    ssl_trusted_certificate /etc/letsencrypt/live/cloud.example.com/chain.pem;

    # TLS hardening
    ssl_protocols             TLSv1.3 TLSv1.2;
    ssl_prefer_server_ciphers off;
    ssl_ciphers               ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_session_cache         shared:MozSSL:10m;
    ssl_session_timeout       1d;
    ssl_session_tickets       off;
    ssl_stapling              on;
    ssl_stapling_verify       on;

    # Match the app's upload limit; stream large uploads instead of buffering to disk.
    client_max_body_size   560M;
    proxy_request_buffering off;
    proxy_read_timeout     300s;

    location / {
        proxy_pass         http://127.0.0.1:8300;
        proxy_http_version 1.1;
        proxy_set_header   Host              $host;
        proxy_set_header   X-Real-IP         $remote_addr;
        proxy_set_header   X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header   X-Forwarded-Proto $scheme;
        proxy_set_header   X-Forwarded-Host  $host;
    }
}
```

Test + reload: `nginx -t && systemctl reload nginx`.

### Apache (httpd)

Enable the modules once: `a2enmod proxy proxy_http ssl headers socache_shmcb` (Debian/Ubuntu). Add `SSLStaplingCache "shmcb:logs/ssl_stapling(32768)"` to the global SSL config for OCSP stapling.

`/etc/apache2/sites-available/ledgerline.conf`:

```apache
<VirtualHost *:80>
    ServerName cloud.example.com
    Redirect permanent / https://cloud.example.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName cloud.example.com

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/cloud.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/cloud.example.com/privkey.pem

    # TLS hardening
    SSLProtocol         -all +TLSv1.3 +TLSv1.2
    SSLHonorCipherOrder off
    SSLUseStapling      on
    SSLSessionTickets   off

    # 560 MiB upload limit (bytes).
    LimitRequestBody    587202560

    ProxyPreserveHost   On
    ProxyPass           / http://127.0.0.1:8300/ timeout=300
    ProxyPassReverse    / http://127.0.0.1:8300/
    RequestHeader set   X-Forwarded-Proto "https"
    RequestHeader set   X-Forwarded-Port  "443"
</VirtualHost>
```

`mod_proxy` sets `X-Forwarded-For`/`X-Forwarded-Host` automatically. Enable + reload: `a2ensite ledgerline && apache2ctl configtest && systemctl reload apache2`.

### Traefik (v3)

Static config `/etc/traefik/traefik.yml`:

```yaml
entryPoints:
  web:
    address: ":80"
    http:
      redirections:
        entryPoint: { to: websecure, scheme: https }
  websecure:
    address: ":443"
    transport:
      respondingTimeouts: { readTimeout: 300s }

certificatesResolvers:
  le:
    acme:
      email: you@example.com
      storage: /etc/traefik/acme.json
      tlsChallenge: {}

providers:
  file:
    filename: /etc/traefik/dynamic/ledgerline.yml

tls:
  options:
    modern:
      minVersion: VersionTLS13
```

Dynamic config `/etc/traefik/dynamic/ledgerline.yml`:

```yaml
http:
  routers:
    ledgerline:
      rule: "Host(`cloud.example.com`)"
      entryPoints: [websecure]
      service: ledgerline
      tls:
        certResolver: le
        options: modern
  services:
    ledgerline:
      loadBalancer:
        passHostHeader: true
        servers:
          - url: "http://127.0.0.1:8300"
```

Traefik forwards the `X-Forwarded-*` headers and streams request bodies (no size cap) by default. If Traefik runs **in Docker**, it cannot reach the host's `127.0.0.1:8300` — either run it on the host network, target `host.docker.internal:8300`, or drop the app's `127.0.0.1` port bind and route Traefik to the `app` service on the compose network with a `docker` provider + labels.

### lighttpd

Enable the modules (`lighttpd-enable-mod proxy` + TLS), then `/etc/lighttpd/conf-available/50-ledgerline.conf`:

```lighttpd
server.modules += ( "mod_proxy", "mod_openssl", "mod_setenv" )

# 560 MiB upload limit — this value is in KiB.
server.max-request-size = 573440

$SERVER["socket"] == ":443" {
    ssl.engine  = "enable"
    ssl.pemfile = "/etc/letsencrypt/live/cloud.example.com/fullchain.pem"
    ssl.privkey = "/etc/letsencrypt/live/cloud.example.com/privkey.pem"
    ssl.openssl.ssl-conf-cmd = ( "MinProtocol" => "TLSv1.3" )

    proxy.server = ( "" => ( ( "host" => "127.0.0.1", "port" => 8300 ) ) )
    proxy.header = ( "https-remap" => "enable" )
    setenv.add-request-header = ( "X-Forwarded-Proto" => "https" )
}

# HTTP → HTTPS
$HTTP["scheme"] == "http" {
    url.redirect = ( "" => "https://${url.authority}${url.path}${qsa}" )
}
```

lighttpd 1.4.46+ `mod_proxy` forwards `X-Forwarded-For` automatically; `server.max-request-size` is in **KiB**. Reload: `systemctl reload lighttpd`.

---

## Configuration reference (environment variables)

Every variable below is read by the application. Names, defaults and required
flags are derived directly from `config/*.php` (`env('NAME', default)`) and the
`.env.example` / `.env.docker.example` files. Where a value is **required**, the
stack will not function without it; everything else has a working default.

> **Note — not everything is an env var.** A few settings are **admin-configurable
> in the database** (workspace Settings UI), *not* through the environment: the
> maximum connected devices per user (overrides `PAIRING_MAX_DEVICES` at runtime),
> per-user / per-group quotas and module access. They are listed here for
> completeness but are set in the UI, not `.env`.

### Application

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `APP_NAME` | Display name of the instance. | `Laravel` (`.env` ships `Ledgerline`) | no |
| `APP_ENV` | Environment. Use `production` in prod, `local` for dev. | `production` | no |
| `APP_KEY` | Laravel app key (`php artisan key:generate --show`). Encrypts sessions + the `encrypted`-cast operational secrets; never part of a DB dump. | — | **yes** |
| `APP_DEBUG` | Debug pages (leak stack traces, env, config). Keep `false` in prod. | `false` | no |
| `APP_URL` | Public URL; must be HTTPS in production. | `http://localhost` | **yes** (prod) |
| `APP_VERSION` | Reported app version. | `1.515.1` (repo value) | no |
| `APP_LOCALE` | Default UI locale (`en`, `de`, `ru`). | `en` | no |
| `APP_FALLBACK_LOCALE` | Locale used when a key is missing. | `en` | no |
| `APP_FAKER_LOCALE` | Faker locale (dev/tests only). | `en_US` | no |
| `APP_MAINTENANCE_DRIVER` | Maintenance-mode driver. | `file` | no |
| `LOG_CHANNEL` | Log channel (`stack` locally, `stderr` in Docker). | `stack` | no |
| `LOG_LEVEL` | Minimum log level. | `debug` (`.env.docker.example`: `warning`) | no |

### Database (PostgreSQL / pgvector)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `DB_CONNECTION` | Must be `pgsql` (PostgreSQL only). | `pgsql` | no |
| `DB_HOST` | Database host (`db` in Docker). | `127.0.0.1` | no |
| `DB_PORT` | Database port. | `5432` | no |
| `DB_DATABASE` | Database name. | `ledgerline` | no |
| `DB_USERNAME` | Database user. | `ledgerline` | no |
| `DB_PASSWORD` | Database password (mounted as a Docker secret file in compose). | — | **yes** |

### Cache / queue / session (Valkey)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `REDIS_CLIENT` | Client library — `predis` (pure PHP, no C extension). | `predis` | no |
| `REDIS_HOST` | Valkey host (`valkey` in Docker). | `127.0.0.1` | no |
| `REDIS_PORT` | Valkey port. | `6379` | no |
| `REDIS_PASSWORD` | Valkey password (mounted as a Docker secret file in compose). | — | **yes** (Docker) |
| `CACHE_STORE` | Cache backend. | `redis` | no |
| `SESSION_DRIVER` | Session store. | `redis` | no |
| `SESSION_LIFETIME` | Session lifetime (minutes). | `120` | no |
| `SESSION_ENCRYPT` | Encrypt session payloads. | `true` | no |
| `SESSION_SECURE_COOKIE` | Emit Secure cookies + HSTS. Set `true` behind TLS. | `true` when `APP_ENV=production` | no |
| `QUEUE_CONNECTION` | Queue backend. | `redis` | no |
| `REDIS_QUEUE_RETRY_AFTER` | Retry window (s); keep above the worker `--timeout`. | `700` | no |

### Object storage / blob disk

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `FILESYSTEM_DISK` | Default Laravel disk. | `local` | no |
| `FILES_DISK` | Disk used for the blob store. `local` (volume) or `files` (S3). | `files` (config) / `local` (`.env` examples) | no |
| `FILES_S3_KEY` | S3 access key id (falls back to `AWS_ACCESS_KEY_ID`). | — | if `FILES_DISK=files` |
| `FILES_S3_SECRET` | S3 secret (or `FILES_S3_SECRET_FILE`; falls back to `AWS_SECRET_ACCESS_KEY`). | — | if `FILES_DISK=files` |
| `FILES_S3_REGION` | S3 region. | `auto` (config) / `us-east-1` (`.env`) | no |
| `FILES_S3_BUCKET` | Private bucket name. | — (falls back to `AWS_BUCKET`) | if `FILES_DISK=files` |
| `FILES_S3_ENDPOINT` | S3 endpoint (for R2/B2/MinIO/Hetzner). | — (falls back to `AWS_ENDPOINT`) | provider-dependent |
| `FILES_S3_USE_PATH_STYLE` | Path-style addressing (`true` for MinIO; `false` for virtual-hosted). | `true` | no |
| `FILES_S3_CHECKSUM_CALCULATION` | `x-amz-checksum-*` behaviour; `when_required` for B2/Hetzner/older MinIO. | `when_required` | no |
| `FILES_S3_CHECKSUM_VALIDATION` | Response checksum validation. | `when_required` | no |
| `FILES_S3_RETRY_MODE` | AWS SDK retry mode (transient multipart 5xx). | `standard` | no |
| `FILES_S3_MAX_ATTEMPTS` | Max retry attempts. | `8` | no |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` / `AWS_DEFAULT_REGION` / `AWS_BUCKET` / `AWS_ENDPOINT` / `AWS_USE_PATH_STYLE_ENDPOINT` / `AWS_URL` | Generic `s3` disk credentials; the `FILES_S3_*` block falls back to these. | — | no |
| `AWS_EC2_METADATA_DISABLED` | `true` — always pass explicit S3 keys; skip the IMDS probe (set in compose). | `true` (compose) | no |

### Authentication (first-party — Laravel Fortify)

Authentication is first-party; there are **no auth env vars to set**. Accounts are
provisioned with `php artisan user:set-password {email} [--admin]` and managed in
the UI (roles, per-user quota/device caps, groups, per-module access).
Self-registration and the password floor are workspace/framework settings, not env:

| Setting | Purpose | Default |
| --- | --- | --- |
| `app_settings.allow_registration` (UI) | Allow public self-registration (with e-mail verification). Off = admins create users. | `false` |
| `users.role` (`admin`/`user`, not fillable) | Admin manages users, limits, groups, workspace settings. Lowest-id user is admin after migration. | first user = `admin` |
| Password policy | `Password::min(12)`, hashed with Argon2id (`HASH_DRIVER`). | 12-char min |
| TOTP 2FA + recovery codes | Optional per-user, confirm-flow (Fortify `two_factor_*`). | opt-in |
| SMTP | Configured in-app (Notifications settings) for reset/verify/invite mail. Absent → use `user:set-password` / invite links. | mail off |

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `TRUSTED_PROXIES` | Private range(s) the host reverse-proxy uses. **Never `*`** in production — it lets a remote client forge `X-Forwarded-For` and spoof its source IP. | none | recommended |

### Device tokens (mobile / CLI)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `SANCTUM_EXPIRATION` | Absolute device-token lifetime (minutes). | `259200` (180 days) | no |
| `DEVICE_IDLE_DAYS` | Revoke a token unused this many days (0 disables). | `90` | no |
| `DEVICE_WIPE_GRACE_MINUTES` | Grace before a remotely-wiped token is hard-revoked. | `15` | no |
| `PAIRING_MAX_DEVICES` | Max paired devices per user (oldest revoked past the cap). Runtime override: the admin `max_connected_devices` setting. | `3` | no |
| `SANCTUM_TOKEN_PREFIX` | Optional token prefix (for secret-scanner detection). | `''` | no |

### Machine learning (image recognition — returning)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `ML_ENABLED` | Enable the ML sidecar (CLIP tagging + smart search). | `false` | no |
| `ML_URL` | Internal URL of the `ml` service. | `http://ml:3003` | no |
| `ML_CLIP_MODEL` | CLIP model name. | `XLM-Roberta-Large-Vit-B-32` (config) / `ViT-B-32__openai` (`.env.docker.example`) | no |
| `FACE_ENABLED` | Enable facial recognition. | `false` | no |
| `ML_FACE_MODEL` | Face model name. | `buffalo_l` | no |
| `GALLERY_FACE_MIN_SCORE` | Minimum face-detection confidence. | `0.7` | no |
| `FILES_SEMANTIC_SEARCH` | Enable semantic file search. | `true` | no |

### Gallery, files, explore (quotas & limits)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `GALLERY_FFMPEG_PATH` | Path to the `ffmpeg` binary (baked into the image). | `ffmpeg` | no |
| `EXIFTOOL_PATH` | Path to `exiftool`. | `exiftool` | no |
| `GALLERY_QUOTA_MB` | Gallery per-user quota MB (0 = unlimited). | `0` | no |
| `GALLERY_MAX_UPLOAD_MB` | Max gallery upload size. | `512` | no |
| `GALLERY_MAX_MEGAPIXELS` | Reject images above this megapixel count. | `120` | no |
| `GALLERY_BLOB_ORPHAN_GRACE_HOURS` | Grace before an unreferenced gallery blob is swept. | `24` | no |
| `GALLERY_SHARE_MAX_MANIFEST_BYTES` | Max public-share manifest size. | `16777216` | no |
| `GALLERY_SHARE_MAX_BLOBS` | Max blobs in a public share. | `16000` | no |
| `FILES_MAX_UPLOAD_MB` | Max file upload size. | `512` (`.env.example`: `2048`) | no |
| `FILES_QUOTA_MB` | Files per-user quota MB (0 = unlimited). | `0` | no |
| `FILES_BLOB_ORPHAN_GRACE_HOURS` | Grace before an unreferenced file blob is swept. | `24` | no |
| `EXPLORE_QUOTA_MB` | Explore track-blob quota MB (0 = unlimited). | `0` | no |
| `EXPLORE_MAX_UPLOAD_MB` | Max explore track-file upload size. | `64` | no |
| `EXPLORE_BLOB_ORPHAN_GRACE_HOURS` | Grace before an unreferenced explore blob is swept. | `24` | no |

### Geocoding (photo GPS → place name)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `GALLERY_GEOCODE_ON_UPLOAD` | Auto-geocode on upload (a boundary crossing; off by default). | `false` | no |
| `PHOTON_URL` | Self-hosted Photon reverse-geocoder URL; queried first so covered points stay in-boundary. | `''` (empty) | no |
| `GEOCODER_URL` | Public fallback geocoder (queried only on a Photon miss). | `https://nominatim.openstreetmap.org` | no |
| `GALLERY_GEOCODE_INTERVAL_MS` | Rate-limit between geocode calls. | `1100` | no |
| `GALLERY_GEOCODE_GRID_KM` | Snap-to-grid size for geocode lookups (privacy). | `0.5` | no |
| `PHOTON_IMPORT_MODE` | Photon container import mode (`db` prebuilt / `jsonl`). | `db` | no |
| `PHOTON_REGION` | Photon coverage region(s). | `germany` | no |

### Maps / Explore auto-routing

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `MAPS_ROUTE_ENGINE` | Routing upstream protocol: `osrm` or `graphhopper`. | `osrm` | no |
| `MAPS_ROUTE_UPSTREAM` | Router base URL. Empty **disables** auto-routing (straight lines only, no egress). | `https://router.project-osrm.org` | no |
| `MAPS_ROUTE_PROFILE` | Routing profile (`foot`/`hike`; must match GraphHopper config). | `foot` | no |
| `GRAPHHOPPER_JAVA_OPTS` / `GRAPHHOPPER_CPU_LIMIT` / `GRAPHHOPPER_MEMORY_LIMIT` | GraphHopper container tuning (compose). | `-Xmx2g -Xms1g` / `2` / `3g` | no |

### Backups

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `BACKUP_PASSPHRASE` | Passphrase for the always-encrypted DB dump. Stored `encrypted`; prefer a mounted secret. | — | recommended |
| `BACKUP_RECONCILE_HOURS` | Full list-and-prune vs. fast incremental delta cadence. | `24` | no |

### Server-side hashing (Argon2id)

The Fortify login password (and the optional public-share password gate) are
hashed server-side with Argon2id.

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `HASH_DRIVER` | Hash driver. | `argon2id` | no |
| `ARGON_MEMORY` | Argon2id memory cost (KiB). | `65536` | no |
| `ARGON_TIME` | Argon2id time cost. | `4` | no |
| `ARGON_THREADS` | Argon2id threads. | `1` | no |
| `BCRYPT_ROUNDS` | bcrypt cost (only if `HASH_DRIVER=bcrypt`). | `12` | no |

### Operations, security & metrics

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `OPS_METRICS_TOKEN` | Bearer for the Prometheus `/metrics` endpoint. Unset → `/metrics` returns 404. | `''` | no |
| `OPS_ERROR_ALERTS` | Send alerts on recorded server errors. | `true` | no |
| `OPS_AUDIT_RETENTION_DAYS` | Retention for the security audit log. | `365` | no |
| `OPS_ACCESS_LOG_RETENTION_DAYS` | Retention for the device access-trail log. | `30` | no |
| `OPS_BACKUP_STALE_HOURS` | Alert threshold for a stale backup. | `48` | no |
| `SECURITY_BLOCK_PRIVATE_HOSTS` | Extra SSRF hardening: block private/link-local hosts on outbound calls. | `false` | no |

### Docker / compose tuning

These are consumed by `docker-compose.yml` (not the application):

| Variable | Purpose | Default |
| --- | --- | --- |
| `IMAGE_TAG` | Tag of the locally-built image. | `local` |
| `APP_PORT` | Host port the app binds to (`127.0.0.1:${APP_PORT}`). | `8300` |
| `APP_MEMORY_LIMIT` | Hard memory ceiling for the web app. | `8g` |
| `WORKER_REPLICAS` / `WORKER_MEMORY_LIMIT` / `WORKER_PHP_MEMORY` | Queue-worker scaling / memory caps. | `1` / `768m` / `512` |
| `SCHEDULER_CPU_LIMIT` / `SCHEDULER_MEMORY_LIMIT` | Scheduler limits. | `1` / `512m` |
| `ML_CPU_LIMIT` / `ML_MEMORY_LIMIT` | ML sidecar limits. | `4` / `8g` |
| `PHOTON_CPU_LIMIT` / `PHOTON_MEMORY_LIMIT` | Photon sidecar limits. | `2` / `4g` |
| `NGINX_CLIENT_MAX_BODY_SIZE` | nginx upload limit (compose). | `560M` |

> **Secrets from files.** Any of `APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`,
> `AWS_SECRET_ACCESS_KEY`, `FILES_S3_SECRET` can be supplied via a `<KEY>_FILE`
> variable pointing at a mounted secret file instead of the plain value — then
> remove the plain value so it never lands in the container environment.

See `.env.example` (local) and `.env.docker.example` (Docker) for annotated,
copy-ready templates.

---

# Security — the full breakdown

Ledgerline stores user content **in the clear at rest** — it is a plaintext-
relational app, not zero-knowledge. Confidentiality against someone who steals the
disk, the database, or a backup is an **infrastructure** responsibility: run on a
LUKS full-disk-encrypted host and take encrypted backups. What the application
itself provides is a hardened **application + transport layer** — strong auth,
strict tenant isolation, encrypted operational secrets, and defence against the
usual web-app attack classes. This section states honestly what is protected and
what is not.

## Threat model: what this design protects and what it does not

**Protected (application/transport):**

- **Remote attackers** — brute force, credential stuffing, session hijacking,
  CSRF, XSS, clickjacking, IDOR / horizontal privilege escalation, SSRF, and
  unauthenticated access are defended (auth + 2FA, owner-scope + policies, CSP,
  security headers, SSRF guard, rate limits — below).
- **DB / backup theft of operational secrets** — SMTP, backup-destination and
  Paperless credentials + the backup passphrase are `encrypted` under `APP_KEY`,
  which is not in a DB dump, so a leaked dump does not expose them.
- **Network eavesdroppers** — TLS 1.3 + HSTS everywhere.

**Not protected by the app (your infrastructure must):**

- **An attacker with the disk, database, or an unencrypted backup** can read all
  user content — file/photo bytes, notes, invoices, GPS tracks. This is the
  deliberate trade of the plaintext-relational model (chosen for queryability,
  server rendering, and to eliminate the client-crypto data-loss class). Mitigate
  with **LUKS full-disk encryption** + **encrypted backups**.
- **A compromised or coerced server operator** can read user content directly.
  Only operational secrets are encrypted at rest; user content (including health
  values) is plaintext in the database.

## Authentication & two-factor

- **First-party** (Laravel Fortify): e-mail + password, hashed with **Argon2id**
  (`Password::min(12)`), with optional **TOTP 2FA** + recovery codes (confirm
  flow). Login is throttled per e-mail + IP, the 2FA challenge is throttled, and
  the session id is regenerated on login. The "public computer" option ends the
  session on browser close.
- **`users.role`** (`admin`/`user`) is a **non-fillable privilege boundary** — set
  only via `forceFill` / the admin controller / the `user:set-password --admin`
  command, never mass-assigned. It drives the workspace-admin gate.
- **Self-registration** is off by default; when enabled, new users are always
  `role = user` and pass e-mail verification.
- **Mail-independent bootstrap/reset:** `php artisan user:set-password` and
  admin-generated **invite links** (single-use, hashed at rest, expiring,
  constant-time compare) provision or reset accounts without SMTP.

## Authorization & tenant isolation

- Single-tenant deployment, **multi-user isolated in code**. The `OwnsUserData`
  trait applies a global owner scope on every read; `AssignsOwner` stamps the
  owner from the authenticated user on create (owner column non-fillable, so it
  cannot be forged). Bulk / destructive / export paths are explicitly owner-scoped.
- Every controller resolves the authenticated user fail-closed
  (`Controller::requireUser`), and per-record endpoints authorize ownership before
  acting — no IDOR across users.
- Admin endpoints (users, backup, security log, groups, per-module access) sit
  behind a **double gate**: `can:manage-global-settings` on top of the normal auth
  middleware, on web **and** `/api/v1`.

## Encrypted operational secrets

Operational secrets never appear in the clear at rest or in responses:

- SMTP credentials, backup-destination credentials (S3/B2/SFTP/WebDAV), the backup
  passphrase, and the Paperless token are stored with Laravel's **`encrypted`
  cast** (AES-256-GCM under `APP_KEY`). `APP_KEY` is not part of a DB dump.
- Backup destination `config` and the job `passphrase` are **never serialized**
  into API responses; a database-target backup without encryption is rejected
  (422) so a DB dump never leaves the host unencrypted.

## Device tokens (mobile / CLI)

The mobile app / CLI pair via a QR / short-code exchange and receive a **Sanctum
bearer** (`abilities:device`). Tokens are **capped per user** (LRU eviction past
the cap), have an absolute expiry, idle-expire, carry per-device abilities, and
can be **remotely wiped** (enforced, not advisory — hard-revoked after a grace).
Every token-destroying path writes a reason-tagged audit entry; a throttled
device access-trail records access (route group only, never full paths).

## Transport, headers, CSP

- **TLS 1.3 + HSTS** (preload) via the host reverse proxy; plain HTTP only
  redirects. Secure / HttpOnly / SameSite session cookies; sessions encrypted.
- **Strict CSP** — `script-src 'self'` (no `unsafe-inline` scripts; a single
  hashed theme-bootstrap; `unsafe-eval` only for Alpine, never over untrusted
  data), `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff`, COOP
  `same-origin`, tight `Permissions-Policy`, `security.txt`. Emitted by
  `SecurityHeaders` on **both** web and API responses.

## Upload handling & untrusted content

- Uploaded file/photo bytes are stored as plaintext on the private blob disk and
  streamed back through **sandboxed** routes: an inline user-supplied file is
  served under a `default-src 'none'; sandbox` CSP (which `SecurityHeaders` does
  not override), so hostile HTML can't execute same-origin — no stored XSS via
  uploads. Rendered types get `nosniff` + immutable cache headers.
- Server-side image processing (renditions, EXIF, pHash) uses ImageMagick with a
  restrictive `policy.xml`; the OCR toolchain (`tesseract` + `poppler-utils`) is
  invoked only via **array-argv** (`Support\BinaryProcess`, no shell string), on
  transient temp files (`Support\DiskTempFile`, RAII-unlinked), with size / MIME /
  page caps, and nothing persisted or logged.

## SSRF-guarded outbound calls

Every outbound HTTP call goes through `App\Support\OutboundUrl`: link-local /
metadata blocking, IP pinning against DNS rebinding, and no redirect following.
This covers geocoding, the ML sidecar, backup destinations, favicon/BIMI fetches,
Paperless, the maps router / link resolver, and ntfy / webhooks / SMTP. Photo
geocoding is grid-snapped and opt-in; keep the ML sidecar internal and self-host
Photon to keep those lookups in-boundary.

## Rate limiting

Rate limits span auth, 2FA, device pairing, invite-link consume, geocoding, the
maps router, receipt OCR, store writes, blob uploads, backups, and admin writes —
per-principal and per-IP — plus array / body / upload size caps.

## Audit logging

Security-relevant events are recorded in a **tamper-evident, hash-chained** audit
log: authentication success/failure, authorization denials, device
pairing/revocation/wipe/eviction (reason-tagged), settings changes (with a
before/after diff), invite-link create/consume, and account export/deletion. Meta
is ids / roles / counts / booleans only — never tokens or secret values. An
admin **Security Log** UI + CSV/JSON export (CSV-injection-neutralised) and the
`audit:show` CLI read it.

## Backups

- **Files / gallery** blobs are mirrored to the destination (incremental with a
  periodic full list-and-prune reconcile).
- **The database dump is always encrypted** (Argon2id SENSITIVE, versioned
  container, minimum passphrase length) before it leaves the host — with the
  passphrase held separately from the backup storage. A database target without
  encryption is refused (422).
- **Destinations:** S3 / Backblaze B2 / SFTP / WebDAV; credentials stored
  `encrypted`; every connection passes the SSRF guard. Restore is **verified**, not
  assumed (dry-run verifier + `php artisan backups:decrypt`).

## Deletion

Records use a soft-delete trash; emptying trash and account deletion remove the
rows and delete the associated disk blobs. GDPR account erasure purges the user's
rows (FK-cascade) and their disk bytes, and streams a ciphertext-free content
inventory on export. Orphaned blobs are swept daily.

## Supply chain & static analysis

- Dependencies pinned (exact versions + Docker image digests), kept at latest
  stable; `composer audit` + `npm audit` (both trees) run **blocking** in CI.
- **PHPStan level 10 (max)** + Pint + Vitest + ESLint + the full test suite gate
  every change; **Rector** dry-run report; **gitleaks** secret scan over the full
  git history (blocking) + a local `pre-push` hook; **Trivy** fs scan; **SBOMs**
  in both SPDX and CycloneDX. A red security-scan job blocks release.

---

## API

The mobile app / CLI authenticate via a **QR device pairing exchange**: scan the
pairing QR from the web profile page (`POST /api/v1/auth/pair`), poll for owner
approval (`POST /api/v1/auth/pair/collect`), and receive a Sanctum bearer token
sent as `Authorization: Bearer <token>` on every subsequent request. All endpoints
are under `/api/v1` (Sanctum, `abilities:device`).

The API is **plaintext REST** — each module is read and written through per-record
JSON endpoints (create/update/delete with optimistic `version` + 409, soft-delete
trash), the exact same controllers the web session uses. There are no sealed-store
/ vault / opaque-blob endpoints; the server reads and renders the data.

See [`openapi.yaml`](openapi.yaml) for the complete machine-readable reference
(OpenAPI 3.1, 221 operations, verified 1:1 against the route table).

---

## Development workflow

- **Git Flow.** `develop` is the working branch; every `main` commit is a tagged
  `vX.Y.Z` release. Merge with `--no-ff`.
- **Gates (all green before a release):** Pint, PHPStan level 10, ESLint, Vitest,
  the full PHP test suite, EN/DE/RU language parity, `openapi.yaml` in sync with
  the route table, and `CLAUDE.md` + the security register updated in the same
  commit.
- **Tests:** `php artisan test --teamcity`. Run `PhotoEditTest` in a filtered chunk
  — it can segfault under imagick/GD and mask later tests.
- **Conventions:** monochrome icons via `<x-icon>` only; EN/DE/RU parity for every
  string; no AI references in code, comments, commits or releases; assets bundled
  locally (no CDNs/telemetry); only `README.md` + `CLAUDE.md` are Markdown.

---

## License

See the repository for licensing terms.
