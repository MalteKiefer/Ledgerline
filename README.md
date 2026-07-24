# Ledgerline

Ledgerline is a **self-hosted, zero-knowledge personal cloud**. Photos, files,
notes, todos, bookmarks, contacts, invoices, health data and passwords live on
your own server, but the server only ever holds **ciphertext** — everything is
encrypted and decrypted in your browser (or in the native apps / browser
extension). Even the person operating the server cannot read your data, and a
full copy of the database and object store yields nothing but opaque blobs.

Authentication is delegated to a [Pocket-ID](https://github.com/pocket-id/pocket-id)
OIDC provider; the application stores no login passwords of its own. All assets
are bundled and served locally — no external CDNs, fonts, or trackers.

> **New to the codebase?** `CLAUDE.md` is the working context + security decision
> log. This README is the maintained feature + security description. The
> machine-readable API contract is [`openapi.yaml`](openapi.yaml).

---

## Contents

- [Modules](#modules)
- [Stack](#stack)
- [Requirements](#requirements)
- [Installation](#installation-docker-compose)
- [Configuration reference](#configuration-reference-environment-variables)
- [**Security — the full breakdown**](#security--the-full-breakdown)
  - [Threat model: what the server can and cannot see](#threat-model-what-the-server-can-and-cannot-see)
  - [The key model (how keys are made and used)](#the-key-model-how-keys-are-made-and-used)
  - [How data is sealed at rest (Store v3)](#how-data-is-sealed-at-rest-store-v3)
  - [Crypto contract: suite envelope, canonical JSON, Padmé](#crypto-contract-suite-envelope-canonical-json-padmé)
  - [Post-quantum hybrid KEM (sharing + identity)](#post-quantum-hybrid-kem-sharing--identity)
  - [Cross-user sharing (vaults & folders)](#cross-user-sharing-vaults--folders)
  - [Public share links](#public-share-links)
  - [Passkeys / WebAuthn](#passkeys--webauthn)
  - [Browser extension](#browser-extension)
  - [Authentication & device tokens](#authentication--device-tokens)
  - [Audit logging](#audit-logging)
  - [Backups](#backups)
  - [Deletion & crypto-shred](#deletion--crypto-shred)
  - [Transport, headers, SSRF](#transport-headers-ssrf)
  - [Residual metadata leakage (honest)](#residual-metadata-leakage-honest)
  - [Supply chain & static analysis](#supply-chain--static-analysis)
- [API](#api)
- [Development workflow](#development-workflow)
- [License](#license)

---

## Modules

- **Dashboard** — a client-side start page aggregating widgets (upcoming todos,
  birthdays/anniversaries, health values + quick-add, "on this day" photos,
  storage usage, counters, password-health snapshot, recent notes). Built purely
  from the already-decrypted stores; no server-side aggregation.
- **Gallery** — photos & videos, HEIC/AVIF + Apple Live Photos, albums, a map
  view, timeline, duplicate detection (pHash + CLIP), and **People** (in-browser
  face clustering + manual tagging). Thumbnails/mediums are derived on-device
  (canvas + WebP) where the browser can decode; ML runs over
  decrypted-in-memory renditions; blobs at rest stay sealed.
- **Files** — a nestable folder browser with versioning, per-user quota, WebDAV
  access, and unified sharing (internal folder shares + public links).
- **Notes / Todos / Bookmarks** — sealed records rendered client-side (Markdown
  via marked + DOMPurify).
- **Passwords** — a full password manager: 9 item types (login, password, card,
  wifi, license, server, passkey, identity, secure note), per-item version
  history, client-side TOTP, password generator, strength (zxcvbn-ts), breach
  check (HIBP k-anonymity), 2FA-directory hints, WiFi-QR, favicon/BIMI, import
  (Bitwarden/LastPass/KeePass/1Password), client-side export, and **shareable
  vaults**.
- **Contacts** — zero-knowledge vCard 4.0 contacts (no CardDAV), encrypted
  avatars, address mini-maps, bidirectional link to gallery People.
- **Invoices** — zero-knowledge invoicing with print/PDF templates.
- **Explore** — a map-centric view unifying gallery photo/video pins with
  self-recorded/imported **GPS tracks** (GPX/KML/KMZ/TCX/FIT, parsed client-side)
  and automatic photo-to-track coupling. Track points, couplings and tolerances
  live sealed in the `explore` store. The map renders with Leaflet + OpenStreetMap
  raster tiles loaded directly in the browser (same as the gallery location
  picker; allowed by the existing `img-src *.tile.openstreetmap.org` CSP) — no
  tile server, relay or `.mbtiles` needed. (Offline vector maps are a later
  iOS-client addition.)
- **Health** — zero-knowledge health tracking (weight, blood pressure, pulse,
  SpO₂, temperature, glucose), charts (uPlot), and a doctor-export.
- **Backup** — encrypted, incremental backups to S3/B2/SFTP/WebDAV.
- **Paperless** — per-user Paperless-ngx integration.
- **Browser extension** (Chromium, MV3) — zero-knowledge password autofill,
  bookmarks CRUD, and a **WebAuthn authenticator** (passkeys).

---

## Stack

| Component     | Version        | Notes                                                   |
| ------------- | -------------- | ------------------------------------------------------- |
| PHP           | 8.5            | `declare(strict_types=1)`, full type hints, PHPStan L10 |
| Laravel       | 13.x           | Framework (`composer.json` requires `^13.8`)            |
| PostgreSQL    | 17 (pgvector)  | `vector` extension for CLIP/face similarity             |
| Valkey        | 8.x            | Cache, session, queue (Redis-compatible, `predis`)      |
| Node.js       | 22 LTS / Vite 8 / Tailwind 4 | Asset build                               |
| Alpine.js     | 3.x (modular)  | `resources/js/app.js` + `shared/*` + `components/*`     |
| libsodium     | wrappers-sumo  | Symmetric client crypto (`resources/js/vault.js`)       |
| @noble/post-quantum + @noble/hashes | pinned | ML-KEM-768 + HKDF (PQ-hybrid KEM)          |
| Laravel Sanctum | 4.x          | Bearer tokens for the mobile/CLI/extension API          |
| sabre/dav     | 4.x            | WebDAV (files-over-WebDAV + a backup destination)       |
| socialiteproviders/pocketid | 5.x | Pocket-ID OIDC provider                            |
| immich-machine-learning | optional | Face detection + CLIP embeddings (profile-gated)      |

---

## Requirements

Ledgerline is designed to run as a Docker Compose stack. To operate it you need:

| Requirement | Version / notes |
| --- | --- |
| **Docker + Docker Compose** | Compose v2 (the `docker compose` plugin). The production image is built locally from the repo `Dockerfile`. |
| **PostgreSQL 17 with pgvector** | Provided by the bundled `db` service (`pgvector/pgvector:pg17`). The `vector` extension backs CLIP / face-similarity duplicate detection. |
| **Valkey 8** | Provided by the bundled `valkey` service. Redis-protocol compatible; used for cache, session and queue via the pure-PHP `predis` client (no `phpredis` extension needed). |
| **A Pocket-ID OIDC provider** | [Pocket-ID](https://github.com/pocket-id/pocket-id) is the sole identity provider — the app has **no local password login**. You must register a Pocket-ID OAuth2 client and provide its base URL, client id/secret and redirect URI. |
| **Object storage (optional but recommended)** | An S3-compatible bucket (Amazon S3, Cloudflare R2, Backblaze B2, Hetzner Object Storage, MinIO, …) for the private `files` blob disk. If omitted, blobs are stored on the local `app-storage` volume. |
| **A TLS-terminating reverse proxy** | Production expects **Caddy on the host** (or any equivalent) in front of the app, which binds to `127.0.0.1:${APP_PORT}` only. The proxy terminates TLS 1.3 and forwards `X-Forwarded-*`. Secure cookies + HSTS are emitted when `SESSION_SECURE_COOKIE=true`. |
| **Node.js 22 LTS** | Only for local (non-Docker) development / building assets. In the Docker build, assets are compiled in a Node 22 stage automatically. |

Optional, profile-gated sidecars (all self-hosted, all off by default):

| Service | Compose profile | Purpose |
| --- | --- | --- |
| `ml` (immich-machine-learning) | `ml` | CLIP object/scene tagging + smart search and facial recognition. Reached over the internal network at `http://ml:3003`. |
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
sessions and a few non-content server-side values; **it never encrypts the user
vault** (that is derived client-side from the vault passphrase).

### 3. Set the required secrets

At minimum, fill these in `.env`:

- `DB_PASSWORD` and `REDIS_PASSWORD` (both **required**) — datastore credentials.
  These are delivered to the `db`/`valkey` containers as mounted secret files, so
  they never appear in `docker inspect` or `/proc/<pid>/environ`.
- `APP_URL` — your public HTTPS URL (e.g. `https://cloud.example.com`).
- `TRUSTED_PROXIES` — the private range(s) your host proxy reaches the container
  over. **Never `*`** in production behind a shared network.

### 4. Configure the Pocket-ID OIDC client

Register an OAuth2 client in your Pocket-ID instance, then set:

```dotenv
POCKETID_BASE_URL=https://id.example.com
POCKETID_CLIENT_ID=…
POCKETID_CLIENT_SECRET=…
POCKETID_REDIRECT_URI=https://cloud.example.com/auth/callback   # = <APP_URL>/auth/callback
```

The redirect URI must be registered verbatim in Pocket-ID. If you run a
multi-user install and want the workspace-admin gate, expose the `groups` claim
and set `POCKETID_ADMIN_GROUP` (see the config table). By default sign-in is
**first-user-wins**: the first subject to authenticate claims the sole account
and all others are rejected. Pin `POCKETID_ALLOWED_SUBS` / `POCKETID_ALLOWED_EMAILS`
to restrict sign-in to explicit subjects / verified e-mails.

### 5. Choose your blob storage

- **Local (default):** leave `FILES_DISK=local` — uploads land in the
  `app-storage` volume. Simplest, no external dependency.
- **S3-compatible:** set `FILES_DISK=files` (Docker) / `FILES_DISK=files` and fill
  the `FILES_S3_*` block (`FILES_S3_KEY`, `FILES_S3_SECRET`, `FILES_S3_BUCKET`,
  `FILES_S3_REGION`, `FILES_S3_ENDPOINT`, `FILES_S3_USE_PATH_STYLE`). The bucket is
  private; the app streams every byte behind auth. All stored bytes are already
  client-side ciphertext.

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
- **`db`** — PostgreSQL 17 + pgvector.
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

The app binds to `127.0.0.1:${APP_PORT}` (default `8300`). Configure Caddy (or
your proxy of choice) on the host to terminate TLS and `reverse_proxy` to that
address; keep `SESSION_SECURE_COOKIE=true` so Secure cookies + HSTS are emitted.
Align the proxy's request-body limit with the app's upload limits
(`NGINX_CLIENT_MAX_BODY_SIZE` / `PHP_POST_MAX_SIZE` are `560M`/`550M` in the image).

### 9. Verify

```bash
docker compose ps                              # every service healthy
curl -fsS https://cloud.example.com/up         # → 200
```

Then open `APP_URL`, sign in through Pocket-ID, and create your vault passphrase
(store the one-time recovery key safely).

### Local development (without Docker)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# configure DB_*, REDIS_*, POCKETID_*, and the "files" S3 disk (MinIO locally)
php artisan migrate
npm run dev            # or: npm run build   (+ npm run build:ext for the extension)
php artisan serve
```

---

## Configuration reference (environment variables)

Every variable below is read by the application. Names, defaults and required
flags are derived directly from `config/*.php` (`env('NAME', default)`) and the
`.env.example` / `.env.docker.example` files. Where a value is **required**, the
stack will not function without it; everything else has a working default.

> **Note — not everything is an env var.** A few security-relevant settings are
> **admin-configurable in the database** (workspace Settings UI), *not* through
> the environment: trusted-device vault-remember days (default **7**),
> public-computer idle-lock minutes (default **10**), and the maximum connected
> devices per user (overrides `PAIRING_MAX_DEVICES` at runtime; default falls back
> to `PAIRING_MAX_DEVICES`). They are listed here for completeness but are set in
> the UI, not `.env`.

### Application

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `APP_NAME` | Display name of the instance. | `Laravel` (`.env` ships `Ledgerline`) | no |
| `APP_ENV` | Environment. Use `production` in prod, `local` for dev. | `production` | no |
| `APP_KEY` | Laravel app key (`php artisan key:generate --show`). Encrypts sessions + a few server-side non-content values; **never** the user vault. | — | **yes** |
| `APP_DEBUG` | Debug pages (leak stack traces, env, config). Keep `false` in prod. | `false` | no |
| `APP_URL` | Public URL; must be HTTPS in production. | `http://localhost` | **yes** (prod) |
| `APP_VERSION` | Reported app version. | `1.505.49` (repo value) | no |
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

### Authentication (Pocket-ID OIDC)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `POCKETID_BASE_URL` | OIDC issuer base URL of your Pocket-ID instance. | — | **yes** |
| `POCKETID_CLIENT_ID` | OAuth2 client id. | — | **yes** |
| `POCKETID_CLIENT_SECRET` | OAuth2 client secret (or `POCKETID_CLIENT_SECRET_FILE`). | — | **yes** |
| `POCKETID_REDIRECT_URI` | OAuth2 redirect. Must equal `<APP_URL>/auth/callback`. | — | **yes** |
| `POCKETID_USE_PKCE` | Use PKCE in the authorization-code flow. | `true` | no |
| `POCKETID_LOGOUT_ENDPOINT` | Optional RP-initiated logout endpoint. | — | no |
| `POCKETID_ADMIN_GROUP` | OIDC group whose members may change global/infra settings (backups of all users, workspace config). Empty is allowed only on a single-user install; **required** for multi-user (fail-closed). | — | multi-user only |
| `POCKETID_ALLOWED_SUBS` | Comma list of OIDC subject ids permitted to sign in (empty = first-user-wins). | — | no |
| `POCKETID_ALLOWED_EMAILS` | Comma list of verified e-mails permitted to sign in. | — | no |
| `TRUSTED_PROXIES` | Private range(s) the host reverse-proxy uses. **Never `*`** in production — it lets a remote client forge `X-Forwarded-For` and spoof its source IP. | none | recommended |

### Device tokens (mobile / CLI / extension)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `SANCTUM_EXPIRATION` | Absolute device-token lifetime (minutes). | `259200` (180 days) | no |
| `DEVICE_IDLE_DAYS` | Revoke a token unused this many days (0 disables). | `90` | no |
| `DEVICE_WIPE_GRACE_MINUTES` | Grace before a remotely-wiped token is hard-revoked. | `15` | no |
| `PAIRING_MAX_DEVICES` | Max paired devices per user (oldest revoked past the cap). Runtime override: the admin `max_connected_devices` setting. | `3` | no |
| `SANCTUM_TOKEN_PREFIX` | Optional token prefix (for secret-scanner detection). | `''` | no |

### Machine learning (image recognition)

| Variable | Purpose | Default | Required |
| --- | --- | --- | --- |
| `ML_ENABLED` | Enable the ML sidecar (CLIP tagging + smart search). | `false` | no |
| `ML_URL` | Internal URL of the `ml` service. | `http://ml:3003` | no |
| `ML_CLIP_MODEL` | CLIP model name. | `XLM-Roberta-Large-Vit-B-32` (config) / `ViT-B-32__openai` (`.env.docker.example`) | no |
| `FACE_ENABLED` | Enable facial recognition. | `false` | no |
| `ML_FACE_MODEL` | Face model name. | `buffalo_l` | no |
| `GALLERY_FACE_MIN_SCORE` | Minimum face-detection confidence. | `0.7` | no |
| `FILES_SEMANTIC_SEARCH` | Enable semantic file search. | `true` | no |

### Gallery, files, contacts, explore (quotas & limits)

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
| `FILES_MAX_UPLOAD_MB` | Max file upload size. | `512` (`.env.example`: `FILES_MAX_UPLOAD_MB=2048`) | no |
| `FILES_QUOTA_MB` | Files per-user quota MB (0 = unlimited). | `0` | no |
| `FILES_VAULT_IDLE_MINUTES` | Fallback idle-lock minutes for the vault. | `10` | no |
| `FILES_BLOB_ORPHAN_GRACE_HOURS` | Grace before an unreferenced file blob is swept. | `24` | no |
| `CONTACTS_QUOTA_MB` | Contacts avatar quota MB (0 = unlimited). | `0` | no |
| `CONTACTS_MAX_UPLOAD_MB` | Max contact-avatar upload size. | `16` | no |
| `CONTACTS_BLOB_ORPHAN_GRACE_HOURS` | Grace before an unreferenced contact blob is swept. | `24` | no |
| `EXPLORE_QUOTA_MB` | Explore track-blob quota MB (0 = unlimited). | `0` | no |
| `EXPLORE_MAX_UPLOAD_MB` | Max explore track-file upload size. | `64` | no |
| `EXPLORE_BLOB_ORPHAN_GRACE_HOURS` | Grace before an unreferenced explore blob is swept. | `24` | no |
| `VAULT_MANIFEST_MAX_BYTES` | Max sealed-manifest size accepted server-side. | `16000000` | no |

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
| `BACKUP_PASSPHRASE` | Passphrase for the always-encrypted DB dump (keeps the key out of the DB that gets dumped). Prefer a mounted secret. | — | recommended |
| `BACKUP_RECONCILE_HOURS` | Full list-and-prune vs. fast incremental delta cadence. | `24` | no |

### Server-side hashing (Argon2id)

Only the optional public-share password gate is hashed server-side — never the
encryption root.

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
> `POCKETID_CLIENT_SECRET`, `AWS_SECRET_ACCESS_KEY`, `FILES_S3_SECRET` can be
> supplied via a `<KEY>_FILE` variable pointing at a mounted secret file instead
> of the plain value — then remove the plain value so it never lands in the
> container environment.

See `.env.example` (local) and `.env.docker.example` (Docker) for annotated,
copy-ready templates.

---

# Security — the full breakdown

Ledgerline's core promise: **the server is a zero-knowledge relay.** It stores
and returns ciphertext, enforces ownership and rate limits, and serves opaque
bytes. It never has the keys to read anything. This section spells out exactly
how that works — the key model, how each thing is sealed, and where the honest
residual leaks are.

## Threat model: what the server can and cannot see

**Assumptions.** The source code is public (no secrets in it). The database and
object store may be exfiltrated in full. Backups may be exfiltrated. The hosting
provider is untrusted for confidentiality. The network is hostile and recorded.
Logs may be read by people who should not see user data. A privileged internal
account may be compromised or coerced. For the extension, the browser and any
web page it runs in are hostile to the extension's secrets.

**The server CAN see:** ciphertext blobs, an opaque sealed "root"
(ciphertext + version + timestamp), a blob-size ledger (padded sizes), approximate
item counts (≈ blob count), and coarse upload timing (snapped to the hour).

**The server CANNOT see:** any plaintext content or metadata — file names, note
bodies, passwords, photo pixels, contact fields, health values, folder
structure, tags, search terms. None of it exists server-side in the clear, ever.
A compelled operator produces only ciphertext.

A concise **STRIDE + LINDDUN** model is maintained in `CLAUDE.md`
(section *Threat Model*), versioned with the architecture.

## The key model (how keys are made and used)

Everything hangs off one secret you know — the **vault passphrase** — which never
leaves your device.

```
passphrase ──Argon2id(ops=4, mem=256 MiB)──► KEK
                                              │ unwraps
                                              ▼
                                        Vault Key (VK, 32 bytes)   ← never leaves the client
                                        │                 │
                     wraps (secretbox)  │                 │  wraps (secretbox)
                                        ▼                 ▼
                          per-blob content keys     module/manifest seals
                          (one per file/photo)      (per-module + sharded stores)
```

Step by step:

1. **Passphrase → KEK.** In the browser, `libsodium` runs **Argon2id** with
   `ops = 4`, `mem = 256 MiB` over your passphrase to derive a Key-Encryption-Key.
   *(These parameters are deliberately fixed at a value that must run in a browser
   — including WASM — and interoperate with mobile clients under their memory
   ceilings. Raising them would break the weakest client, not add real security.)*
2. **KEK → Vault Key (VK).** The KEK unwraps your **per-user Vault Key** — a random
   32-byte key. The VK is the root symmetric secret. **It never leaves the client**
   and is never sent to the server.
3. **VK seals everything symmetric.** Manifests/module stores are sealed with
   **XChaCha20-Poly1305 secretbox** under the VK. Each file/photo gets its own
   random **content key**; the bytes are sealed with XChaCha20-Poly1305
   **secretstream**, and the content key is `secretbox`-wrapped under the VK. So
   one leaked wrapped content key exposes exactly one blob — never the VK, never
   the library.
4. **Recovery key.** At setup a second wrap of the VK is produced under a random
   32-byte **recovery key** (shown once), so you can regain access if you forget
   the passphrase.
5. **Trusted-device persistence.** On a device you mark "trusted", the VK is
   wrapped with a **non-extractable AES-256-GCM key** held in IndexedDB (WebCrypto,
   fresh IV per wrap, bound to your user id) and kept for `VAULT_REMEMBER_DAYS`.
   On a "public computer" the VK is session-only with a short idle lock. Logout
   clears both.
6. **Identity keypair (for sharing).** Separately, each user has an asymmetric
   **hybrid identity keypair** (X25519 + ML-KEM-768) used *only* to wrap keys to
   other users — see [Post-quantum hybrid KEM](#post-quantum-hybrid-kem-sharing--identity).
   Its secret halves are sealed under the VK; the public halves are published.

The heavy per-photo/-file decryption runs in a bounded **Web Worker pool**, so the
UI thread never holds the whole library and workers only ever receive a
single unwrapped content key — never the VK.

## How data is sealed at rest (Store v3)

There is **no monolith**. Each module owns its own opaque sealed store, so a write
to one module never re-seals the others.

- **Per-module stores** (`module_stores`, one row per `(user, module)`): notes,
  todos, bookmarks, contacts, invoices, passwords, health, sharing. Endpoint
  `GET/PUT /store/{module}` — read the sealed blob, decrypt + mutate client-side,
  write it back with optimistic-concurrency (version + ETag/304, 409 on conflict).
  An allow-list rejects unknown modules with 404.
- **Files** and **Gallery** use a **sharded** profile (`files_store`,
  `gallery_store`): a small sealed root pointer table + **content-addressed,
  id-bucketed shard blobs** holding the records + per-record media blobs. A record
  edit re-seals exactly one shard bucket + the tiny root — no cascade, and all
  clients agree on a record's bucket regardless of insert order.
- **Blobs** (photo/file/contact-avatar/shared-folder bytes) are separate opaque
  ciphertext objects on the `files` disk, tracked by a size ledger for quota +
  garbage collection. The server streams them raw.

The server code paths (`ModuleStoreController`, `FilesStoreController`,
`GalleryStoreController`, `BlobStoreController`) never decode or inspect the
ciphertext.

## Crypto contract: suite envelope, canonical JSON, Padmé

These three rules make the ciphertext both crypto-agile and cross-client-stable
(the same manifest is byte-reproducible from a web, iOS, Go-CLI, or Android
client, gated by shared conformance fixtures/KATs in the repo).

- **Suite envelope.** Every sealed manifest carries a `suite` tag (`suite = 1` is
  the current baseline). An unknown suite **fails closed** on the client — the code
  never guesses the crypto stack.
- **Canonical JSON.** Anything sealed or hashed is serialized with keys sorted by
  Unicode scalar, compact separators, **no Unicode normalization** (the only truly
  byte-stable rule across languages), integers only in "hot" records, and decimals
  as fixed 6-dp strings. This makes shard hashes reproducible everywhere.
- **Padmé padding.** Blobs are padded to Padmé buckets and manifests to a Padmé
  bucket with a 4 KiB floor, so stored ciphertext sizes leak only `O(log log n)`
  bits about content size instead of the exact length. Padmé is applied on **every**
  seal path, including the browser extension.

## Post-quantum hybrid KEM (sharing + identity)

The **symmetric core** (VK, blob/manifest sealing) is already safe against a
quantum adversary — it's XChaCha20-Poly1305 with a 256-bit key. The only place
classical asymmetric crypto would matter is **wrapping a key to another user**, so
that path is post-quantum **hybrid**:

- **Algorithm.** X25519 (classical) **+** ML-KEM-768 (FIPS 203, post-quantum),
  combined with **HKDF-SHA-256** over `ss_ec ‖ ss_pq` with info
  `"ledgerline/kem/v1" ‖ context`. Confidentiality holds unless **both** primitives
  fall (a PQXDH-style hybrid). Libraries: `@noble/post-quantum` + `@noble/hashes`
  on web and extension; the native clients use their platform FIPS-203 libs.
- **Envelope.** `{ suite: 1, epk, kem_ct, c, n }` — an ephemeral X25519 public key,
  the ML-KEM ciphertext, and the wrapped payload. Fail-closed on `suite ≠ 1` or an
  authentication failure.
- **Identity secret = 64-byte seed.** The published `wrapped_mlkem_secret_key`
  stores the **64-byte FIPS-203 seed** (sealed under the VK), not the expanded
  decapsulation key. The seed is the portable canonical secret: `keygen(seed)` on
  every platform reproduces the identical keypair, so iOS
  (`PrivateKey(seedRepresentation:)`), web/noble, Go and Android interoperate. The
  dk is regenerated from the seed at decapsulate time.
- **Generated once, never regenerated.** Regenerating an identity would orphan
  every vault key already wrapped to it. This protects shared key material against
  *harvest-now-decrypt-later*.

Passkeys stay classical (P-256 / ES256) — WebAuthn relying parties don't yet
accept post-quantum COSE signature algorithms. This is an accepted, documented
deviation; it affects only the signature scheme, not confidentiality.

## Cross-user sharing (vaults & folders)

Sharing a password vault or a file folder with another registered user is fully
zero-knowledge:

- Each shared vault/folder has its own **VK_vault / VK_folder**. On invite, that
  key is **hybrid-wrapped** (above) to the recipient's published identity and
  stored as `wrapped_vault_key` ciphertext. The server never sees it in the clear.
- **TOFU fingerprint verification.** The inviter's client recomputes the
  recipient's key fingerprint and compares it to the trusted value stored in the
  sealed `sharing` store; a changed fingerprint **blocks** the share (key-swap /
  malicious-server defence).
- **Accept** unwraps the vault key with the invitee's own hybrid identity —
  success proves the key was sealed to the real recipient.
- **Rotate-on-removal.** Removing a member triggers a full **re-key** (fresh
  VK_vault, manifest re-sealed, all remaining members re-wrapped) in one atomic
  server transaction. This is real cryptographic revocation of *future* access —
  not just an ACL flip. (Inherent caveat: a removed member who cached the old
  plaintext keeps that snapshot; they get no new bytes.)
- **Authorization** is fail-closed: `SharedVaultPolicy` has no owner/admin bypass,
  role checks require an active membership, and every denial is a **404** (hides
  existence). Shared-folder blobs count against the *folder owner's* quota, with
  the owner id stamped server-side (never the uploader).

## Public share links

`/s/{token}` links (a gallery album, a single file, or a folder) are a separate,
deliberately **symmetric** format:

- A fresh random 32-byte **share key lives only in the URL fragment** (`#…`), which
  browsers never send to the server. The manifest + referenced blobs are sealed
  under that key. The server only serves the sealed manifest and the owner's
  allow-listed blob refs.
- Optional **password gate** (Argon2id, hard rate-limited, constant-time compare,
  no oracle), optional expiry, optional download flag.
- Blobs are served with immutable, sandboxed, `nosniff` headers; unknown/expired
  tokens 404 uniformly (no existence oracle).

There is no asymmetric key exchange here — the fragment key is the only key — so
the post-quantum KEM does not apply to public links.

## Passkeys / WebAuthn

The browser extension is a full **WebAuthn authenticator**. Passkeys (standalone
`passkey` items or embedded in `login` items) are ES256 (P-256) credentials whose
private JWK is **sealed in the vault** and only ever exists transiently in the
extension's background service worker during a create/get — never on disk, never
logged, never sent to the server. Origin binding is set from the trusted
content-script context (`rpIdAllowed`, dot-boundary + bare-TLD rejection);
`none`-attestation with a zero AAGUID (no fingerprinting); user-verification =
vault-unlock.

## Browser extension

Manifest V3, all code shipped in-package (no remote code, strict extension CSP,
no `eval`). It **reuses the exact same crypto** as the web client (shared
canonical JSON, suite envelope, Padmé, and the hybrid-KEM unwrap) — not a second
implementation — and is gated by the same conformance fixtures.

- The VK is derived **in the extension**, kept only in `chrome.storage.session`,
  and never written to `chrome.storage.local` in plaintext (that holds only
  ciphertext + non-secret state). The MV3 service worker is non-persistent, so no
  plaintext or VK survives a restart — it is re-derived behind unlock.
- Server/data access is exclusively via `/api/v1` (Sanctum device token). Broad
  host permissions exist only for autofill DOM access on login pages, not for data.
- Shared vaults are **read-only** in the extension (it unwraps with the hybrid
  seed but never wraps). Content scripts hold no secrets; the page is treated as
  hostile; messages are origin/sender-validated.

## Authentication & device tokens

- **Sign-in** via Pocket-ID (OIDC, **PKCE + state**), matched on the stable `sub`.
  The app stores no login passwords. A `groups` claim drives the admin gate
  (fail-closed in multi-user).
- **Vault unlock** is separate from sign-in (Proton-style): after signing in you
  enter your vault passphrase once (trusted-device persistence vs. public-computer
  idle lock as above).
- **Mobile / CLI / extension pairing.** A signed-in owner authorises a new device
  by approving a QR (app) or a short-lived code (CLI/extension); the device
  collects a one-time Sanctum bearer. Tokens are **capped per user, expire,
  idle-expire, carry per-device abilities, and can be remotely wiped** (enforced,
  not advisory — after a self-erase grace the token is hard-revoked).

## Audit logging

Security-relevant events are recorded in a **tamper-evident** audit log
(hash-chained entries): authentication success/failure, authorization denial,
device pairing/revocation/wipe, account export/deletion, settings changes, and —
crucially — **all sharing and key events**: vault create/delete, member
invite/remove/accept/role-change, **rotate (revocation)**, identity key publish,
public-share create, and the destructive `store:reset-v3` clean-slate command.
The log records ids/roles/counts only — **never** tokens, ciphertext bodies, keys,
or any decrypted value.

## Backups

Backups are zero-knowledge-aware and incremental.

- **Files / gallery** are mirrored blob-by-blob. Blobs are already client-side
  ciphertext, so they are copied as-is; routine runs upload only blobs added since
  the last high-water mark, with a full list-and-prune reconcile every
  `BACKUP_RECONCILE_HOURS`.
- **The database dump** carries sealed rows + wrapped vault-key material and is
  therefore **always encrypted** (Argon2id SENSITIVE, versioned container,
  minimum passphrase length) before it leaves the host — with keys held separately
  from the backup storage.
- **Destinations:** S3 / Backblaze B2 / SFTP / WebDAV; credentials stored
  encrypted; every connection passes the SSRF guard.
- **Restore** is verified, not assumed: a dry-run verifier checks integrity + the
  passphrase; `php artisan backups:decrypt` decrypts an archive on the CLI.

## Deletion & crypto-shred

Deletion is **key destruction**: dropping a record + deleting its blobs leaves
inert ciphertext. GDPR erasure is a full crypto-shred — it deletes module stores,
blob ledgers, disk blobs (including shared-folder bytes, synchronously), shared
vaults/members, public shares, and the user's wrapped keys, so nothing recoverable
remains via any access path. Orphaned blobs are swept daily. The
`store:reset-v3` command is the ops-gated bulk clean-slate (audited + alerted).

## Transport, headers, SSRF

- **TLS 1.3 + HSTS** (preload) via Caddy on the host; plain HTTP only redirects.
- **Strict CSP** (no `unsafe-inline` scripts; a single hashed theme-bootstrap),
  `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff`, COOP `same-origin`,
  tight `Permissions-Policy`, `security.txt`. Untrusted blobs served under
  `default-src 'none'; sandbox`.
- **SSRF guard** (`App\Support\OutboundUrl`) on **every** outbound call — geocoding,
  the ML sidecar, backup destinations, favicon/BIMI, HIBP, 2fa.directory, ntfy /
  webhooks / SMTP — with link-local/metadata blocking, IP pinning against DNS
  rebinding, and no redirects.
- **Rate limiting** across auth, pairing, recipient lookup, geocoding, ML, store
  writes, blob uploads, backups and public-share unlock; per-principal and per-IP;
  array/manifest size caps and streaming caps.

Deliberate, user-initiated, SSRF-guarded boundary crossings (never automatic):
the optional **ML sidecar** (transiently-decrypted photo bytes for faces / CLIP,
RAII-unlinked in-request) and **geocoding** (grid-snapped lookups). Keep the ML
sidecar internal and self-host Photon/Nominatim to keep these in-boundary.

## Residual metadata leakage (honest)

Zero-knowledge hides *content*; some *metadata* is inherent to any blob store and
is mitigated, not eliminated:

- **Item count ≈ blob count** — visible to the server. Inherent.
- **Blob sizes** → mitigated by client-side **Padmé** padding.
- **Upload timing** → mitigated by snapping `created_at` to the hour.
- **Access pattern** → mitigated by immutable content-addressed caching + uniform
  404-hiding.

New features are reviewed against a metadata-leakage table (in `CLAUDE.md`) so
they don't add a new observable.

## Supply chain & static analysis

- Dependencies pinned (exact versions + Docker image digests), kept at latest
  stable; `composer audit` + `npm audit` (both trees) run **blocking** in CI.
- **PHPStan level 10 (max)** + Pint + Vitest + ESLint + the full test suite gate
  every change; **Rector** dry-run report; **gitleaks** secret scan over the full
  git history (blocking) + a local `pre-push` hook; **Trivy** fs scan; **SBOMs**
  in both SPDX and CycloneDX. A red security-scan job blocks release.

---

## API

The mobile app / CLI / extension authenticate via a **QR device pairing exchange**:
scan the pairing QR from the web profile page (`POST /api/v1/auth/pair`), poll for
owner approval (`POST /api/v1/auth/pair/collect`), and receive a Sanctum bearer
token sent as `Authorization: Bearer <token>` on every subsequent request. All
endpoints are under `/api/v1`.

**Zero-knowledge:** every content payload — `ciphertext`, `sealed_manifest`, blob
upload bodies, `wrapped_vault_key` (a hybrid envelope), `wrapped_mlkem_secret_key`
(a sealed seed) — is opaque ciphertext the server stores and returns without
reading. Module records are read/written via `GET/PUT /store/{module}`; the files
index via `GET/PUT /files/store`; gallery via `GET/PUT /gallery/store`. There is
**no per-record endpoint** (that would break zero-knowledge).

See [`openapi.yaml`](openapi.yaml) for the complete machine-readable reference
(OpenAPI 3.1, 95 operations, verified 1:1 against the route table).

---

## Development workflow

- **Git Flow.** `develop` is the working branch; every `main` commit is a tagged
  `vX.Y.Z` release (app + extension carry the same version). Merge with `--no-ff`.
- **Gates (all green before a release):** Pint, PHPStan level 10, ESLint, Vitest,
  the full PHP test suite, EN/DE/RU language parity, a zero-knowledge scan (no new
  plaintext columns / server render paths), `openapi.yaml` in sync, and `CLAUDE.md`
  + the security register updated in the same commit.
- **Tests:** `php artisan test --teamcity`. Run `PhotoEditTest` in a filtered chunk
  — it can segfault under imagick/GD and mask later tests.
- **Conventions:** monochrome icons via `<x-icon>` only; EN/DE/RU parity for every
  string; no AI references in code, comments, commits or releases; assets bundled
  locally (no CDNs/telemetry); only `README.md` + `CLAUDE.md` are Markdown.

---

## License

See the repository for licensing terms.
