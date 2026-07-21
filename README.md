# Ledgerline

Ledgerline is a **self-hosted, zero-knowledge personal cloud**. It keeps your
photos, files, notes, todos, bookmarks and contacts on your own server while the
server itself only ever holds **ciphertext** — everything is encrypted and
decrypted in your browser. Even the person running the server cannot read your
data.

Authentication is delegated to a [Pocket-ID](https://github.com/pocket-id/pocket-id)
OIDC provider; the application stores no login passwords of its own. All assets
are bundled and served locally — no external CDNs, fonts, or trackers.

---

## What "zero-knowledge" means here

- **Content is encrypted client-side.** A per-user vault key is derived in the
  browser from your passphrase (libsodium Argon2id) and never leaves it. File and
  photo bytes are sealed with XChaCha20-Poly1305 **before** upload.
- **The server stores only opaque blobs and sealed manifests.** Each module's
  data (notes, bookmarks, todos, files, contacts) lives as ciphertext in a single
  sealed workspace manifest; the gallery uses its own sharded sealed manifest.
  The server sees ciphertext + a version + a timestamp, nothing else.
- **Metadata is padded.** Blobs are padded to Padmé buckets and manifests to a
  Padmé bucket with a 4 KiB floor, so stored sizes don't reveal content sizes.
- **The database backup is treated as sensitive.** A DB dump contains the sealed
  ciphertext rows plus the wrapped vault-key material, so backup archives are
  **force-encrypted** (Argon2id SENSITIVE) before they leave the host.

Deliberate, user-initiated boundary crossings (documented in code): the optional
**machine-learning sidecar** receives transiently-decrypted photo bytes to detect
faces / build search embeddings, and **address/place geocoding** sends a lookup to
OpenStreetMap Nominatim. Both are optional and never automatic on upload; both go
through the app's SSRF guard. Self-host Nominatim/Photon and keep the ML sidecar
on the internal network to keep these in-boundary.

---

## Modules

- **Gallery** — photos & videos, HEIC/AVIF + Apple Live Photos, albums, a map
  view, timeline, duplicate detection (pHash + CLIP), and **People**: in-browser
  face clustering with manual face tagging that trains recognition. All ML runs
  over decrypted-in-memory renditions; blobs at rest stay sealed.
- **Files** — a nestable folder browser with versioning and per-user quota.
- **Notes / Todos / Bookmarks** — sealed records rendered client-side (Markdown
  via marked + DOMPurify).
- **Contacts** — zero-knowledge vCard 4.0 contacts (no CardDAV), encrypted
  avatars, address mini-maps, and a bidirectional link to gallery People.
- **Backup** — encrypted, incremental backups to S3/B2/SFTP/WebDAV (see below).
- **Global search & dashboard** across all modules, all client-side.

---

## Stack

| Component     | Version        | Notes                                                   |
| ------------- | -------------- | ------------------------------------------------------- |
| PHP           | 8.4            | `declare(strict_types=1)`, full type hints              |
| Laravel       | 13.x           | Framework                                               |
| PostgreSQL    | 17 (pgvector)  | `vector` extension for CLIP/face similarity             |
| Valkey        | 8.x            | Cache, session, queue (Redis-compatible, `predis`)      |
| Node.js       | 22 LTS / Vite 8 / Tailwind 4 | Asset build                               |
| Alpine.js     | 3.x            | Single-file frontend (`resources/js/app.js`)            |
| libsodium     | wrappers-sumo  | Client crypto (`resources/js/vault.js`)                 |
| Laravel Sanctum | 4.x          | Bearer tokens for the mobile/CLI API                    |
| sabre/dav     | 4.x            | WebDAV (files-over-WebDAV + a backup destination)       |
| socialiteproviders/pocketid | 5.x | Pocket-ID OIDC provider                            |
| immich-machine-learning | optional | Face detection + CLIP embeddings (profile-gated)      |

---

## Deployment (Docker + host Caddy)

Production runs as a Docker Compose stack; TLS + routing are handled by **Caddy
on the host**, reverse-proxying to `127.0.0.1:${APP_PORT}`.

```bash
cp .env.docker.example .env      # fill APP_KEY, DB/REDIS passwords, POCKETID_*, S3
docker compose build
docker compose up -d             # app + worker + scheduler + db + valkey
docker compose --profile ml up -d   # optionally add the ML sidecar
```

Services: `app` (nginx + php-fpm, runs migrations on start), `worker`
(`queue:work`, scale with `--scale worker=N`), `scheduler` (`schedule:work`),
`db` (pgvector/pg17), `valkey`, and the optional `ml` sidecar. Every service runs
with `no-new-privileges` and drops `CAP_NET_RAW`. The app port is bound to
`127.0.0.1` only — put Caddy in front for TLS.

Health check: `curl -fsS https://<your-domain>/up` → `200`.

### Local development (without Docker)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# configure DB_*, REDIS_*, POCKETID_*, and the "files" S3 disk (MinIO locally)
php artisan migrate
npm run dev            # or: npm run build
php artisan serve
```

---

## Environment variables

| Variable | Purpose |
| --- | --- |
| `APP_KEY` | Laravel app key (`php artisan key:generate --show`). |
| `APP_URL` | Public URL; must be HTTPS in production. |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | PostgreSQL (pgvector) connection. |
| `REDIS_CLIENT` | `predis` (pure PHP) — no `phpredis` extension needed. |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Valkey connection. |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | All `redis` → Valkey. |
| `POCKETID_BASE_URL` / `POCKETID_CLIENT_ID` / `POCKETID_CLIENT_SECRET` / `POCKETID_REDIRECT_URI` | Pocket-ID OIDC client. Redirect URI = `<APP_URL>/auth/callback`. |
| `POCKETID_ADMIN_GROUP` | OIDC group whose members may change global/infra settings (fail-closed in multi-user). |
| `AWS_*` / `FILES_S3_*` | The `files` blob disk (Hetzner/S3/R2/MinIO). Bucket is private; app streams all bytes behind auth. |
| `AWS_EC2_METADATA_DISABLED` | `true` — always pass explicit S3 keys; skip the IMDS probe. |
| `TRUSTED_PROXIES` | The private ranges the host reverse-proxy uses (e.g. `10.0.0.0/8,172.16.0.0/12,192.168.0.0/16`). **Never `*`** — that lets a remote client forge `X-Forwarded-For`. |
| `ML_ENABLED` / `ML_URL` / `FACE_ENABLED` / `ML_FACE_MODEL` | Machine-learning sidecar (optional). `ML_URL` defaults to `http://ml:3003`. |
| `SANCTUM_EXPIRATION` | Absolute device-token lifetime in minutes (default 180 days). |
| `DEVICE_IDLE_DAYS` | Revoke a device token unused this many days (default 90; 0 disables). |
| `DEVICE_WIPE_GRACE_MINUTES` | Grace before a remotely-wiped token is hard-revoked (default 15). |
| `PAIRING_MAX_DEVICES` | Max paired devices (app + CLI) per user (default 3). |
| `BACKUP_RECONCILE_HOURS` | How often a mirror backup does a full list-and-prune vs. a fast incremental delta (default 24). |
| `OPS_METRICS_TOKEN` | Bearer for the Prometheus `/metrics` endpoint. Unset → `/metrics` returns 404. |
| `VAULT_REMEMBER_DAYS` / `VAULT_PUBLIC_IDLE_MINUTES` | Trusted-device vault-unlock persistence vs. public-computer idle lock. |

See `.env.example` (local) and `.env.docker.example` (Docker) for the full set.

---

## Authentication & access model

- **Sign-in** goes through Pocket-ID (OIDC, PKCE). Accounts are matched on the
  stable `sub`. The app stores no login passwords.
- **Per-user isolation.** Every module is scoped to its owner (`OwnsUserData` /
  `AssignsOwner`); one server can host many independent users.
- **Vault unlock (Proton-style).** After signing in you enter your vault
  passphrase once. On a **trusted device** the key persists across restarts for
  `VAULT_REMEMBER_DAYS` (wrapped by a non-extractable key in IndexedDB, bound to
  your user id). Ticking **public computer** keeps a session-only key with a
  short idle lock. Logout clears both.
- **Mobile / CLI pairing.** A signed-in owner authorises a new device from the
  profile page — scan a QR (app) or copy a short-lived code (CLI). The device
  collects a one-time Sanctum bearer. Tokens are capped per user, expire, idle-
  expire, and can be **remotely wiped** (the wipe is enforced, not advisory —
  after a self-erase grace the token is hard-revoked).

---

## Backups

Backups are zero-knowledge-aware and incremental.

- **Files / gallery** are mirrored blob-by-blob. Blobs are already client-side
  ciphertext, so they're copied as-is. Routine runs upload only the blobs added
  since the last run (a high-water mark over the blob ledger); a full
  list-and-prune reconcile that removes deletions runs once per
  `BACKUP_RECONCILE_HOURS`. Backing up a large library every few hours stays fast.
- **The database dump** carries sealed rows plus wrapped vault-key material and is
  therefore **always encrypted** (Argon2id SENSITIVE, versioned container format,
  minimum passphrase length) before upload.
- **Destinations:** S3 / Backblaze B2 / SFTP / WebDAV, credentials stored
  encrypted. Every destination connection passes the SSRF guard.
- **Restore** is non-destructive to verify: a dry-run verifier checks integrity +
  the passphrase; `php artisan backups:decrypt` decrypts an archive on the CLI.

---

## Security posture

- **Zero-knowledge at rest** — server holds only ciphertext blobs + sealed,
  size-padded manifests.
- **SSRF guard** (`App\Support\OutboundUrl`) on every outbound call — geocoding,
  the ML sidecar, backup destinations, notification webhooks — with IP pinning and
  metadata/link-local blocking.
- **Strict CSP / HSTS**, script-less CSP for public share pages, sandboxed
  iframes, nosniff; no `unsafe-inline` scripts.
- **Rate limiting** across auth, pairing, geocoding, ML, store writes, blob
  uploads, backups and WebDAV; array/manifest size caps and streaming caps.
- **Owner-scoped everything**, including bulk/destructive paths that bypass
  Eloquent events.
- **Device-token lifecycle** — bounded lifetime, idle revocation, enforced remote
  wipe, per-device abilities.
- **In-app error log + Prometheus `/metrics`** (token-gated) instead of shipping
  data to a third-party APM.

---

## API

The mobile app authenticates via a **QR device pairing exchange**: scan the
pairing QR from the web profile page (`POST /api/v1/auth/pair`), poll for
owner approval (`POST /api/v1/auth/pair/collect`), and receive a Sanctum
bearer token that is sent as `Authorization: Bearer <token>` on every
subsequent request. All endpoints are under `/api/v1`.

**Zero-knowledge:** every content payload — `ciphertext`, `sealed_manifest`,
blob upload bodies, `wrapped_vault_key` — is opaque ciphertext that the server
stores and returns without reading. Decryption happens exclusively in the
client using the vault key that never leaves the browser.

See [`openapi.yaml`](openapi.yaml) for the complete machine-readable API
reference (OpenAPI 3.1, 59 paths / 67 operations).

---

## Development workflow

- **Git Flow.** `develop` is the working branch; every `main` commit is a tagged
  `vX.Y.Z` release. Merge with `--no-ff`.
- **Tests:** `php artisan test --teamcity`. Run in filtered chunks — `PhotoEditTest`
  can segfault under imagick/GD and mask later tests.
- **Conventions:** monochrome icons via `<x-icon>` only; EN/DE language parity for
  every string; no AI references in code, comments, commits or releases; assets
  bundled locally (no CDNs/telemetry).

---

## License

See the repository for licensing terms.
