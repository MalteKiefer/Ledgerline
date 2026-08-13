<div align="center">

# Ledgerline

**A self-hosted, plaintext-relational personal cloud.**
Invoicing, files, contacts, calendar and a full mail archive — one app you run on your own box.

[![License: MIT](https://img.shields.io/badge/License-MIT-2ea44f.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777bb4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-ff2d20.svg?logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue 3](https://img.shields.io/badge/Vue-3-42b883.svg?logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![Vite 8](https://img.shields.io/badge/Vite-8-646cff.svg?logo=vite&logoColor=white)](https://vite.dev/)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-4169e1.svg?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![PHPStan level 10](https://img.shields.io/badge/PHPStan-level%2010-2a5fdd.svg)](https://phpstan.org/)
[![Self-hosted](https://img.shields.io/badge/self--hosted-Docker-2496ed.svg?logo=docker&logoColor=white)](#installation)

</div>

---

## What is Ledgerline?

Ledgerline is a single application that replaces a handful of separate self-hosted
services with one coherent, owner-scoped tool. It is **plaintext-relational**: every
module is an ordinary relational table (one row per record), so your data stays
queryable, searchable, joinable and easy to back up — no opaque blobs, no client-side
crypto to lock you out of your own data.

Confidentiality at rest is an **infrastructure** concern (full-disk encryption +
encrypted backups), not application paranoia. What the app enforces hard is the
**access** boundary: TLS/HSTS, mandatory two-factor auth, a strict CSP, per-record
owner-scope on every endpoint, and encrypted-at-rest **operational secrets** only
(SMTP/IMAP passwords, backup passphrases, PGP/S-MIME private keys — never in a DB dump).

It ships as a **Vue 3 single-page app** on a **Laravel** API, and every `/api/v1`
endpoint is documented in [`openapi.yaml`](openapi.yaml) so native mobile clients build
against a stable contract.

## Modules

| Module | What it does |
|---|---|
| **Finance** | Invoices (GoBD-compliant numbering, ZUGFeRD/Factur-X e-invoice, PDF + email), payment methods, bank-statement import (MT940/CSV) with receipt matching, standalone receipts, projects, business partners, VAT-return / EÜR reports, duplicate detection, category suggestions. |
| **Files** | Nested folders + files, whole & chunked upload, streamed download, version history, trash, tags/labels/notes/favorites, server thumbnails, full-text + OCR content search, ZIP/bulk, storage stats, **sharing** (cross-user + public links), WebDAV mount. |
| **Contacts** | vCard 4.0 address books, CardDAV sync, VCF import/export, photo crop, duplicate detection + merge, map links. |
| **Calendar** | Events with recurrence, CalDAV sync, ICS import/export, holiday & school-holiday calendars. |
| **Mail archive** | Pull-only IMAP archival (mbsync → plaintext `.eml`), server-side full-text search, sandboxed HTML reader, attachments (save to Files/Paperless), threading, labels, ingest rules, saved searches, `.eml`/`.mbox`/ZIP export, server-side PGP/S-MIME reading, push-back / delete-from-origin, and **SMTP send / reply / forward**. |

Plus infrastructure that isn't a "module": first-party auth (Laravel Fortify — e-mail +
password + TOTP 2FA), multi-user admin & groups, an admin **security portal** (verbose
request log + IP/user blocking), backups (S3/B2/SFTP/WebDAV, GFS rotation, restore),
Paperless integration, notifications (SMTP/ntfy/webhook), device pairing for mobile, and
a company/invoice profile.

## Architecture

- **Backend** — Laravel 13 · PHP 8.5 (`declare(strict_types=1)`, PHPStan level 10) ·
  PostgreSQL 18 with pgvector · Valkey 8 (queues/cache) · Sanctum bearer tokens for
  mobile/CLI · sabre/dav for WebDAV + CardDAV + CalDAV on one unified `/dav` server.
- **Frontend** — Vue 3 + `<script setup>` TypeScript · Vite 8 · Pinia · Vue Router ·
  Tailwind 4 · Reka UI · laravel-vue-i18n (English / German / Russian). No external CDNs.
- **Storage** — user bytes live plaintext on the files disk (`BlobStore`); metadata is
  relational. Ships with Docker (all images digest-pinned).
- **Contract** — [`openapi.yaml`](openapi.yaml) is the authoritative REST reference, kept
  1:1 with the routes in CI.

## Security model

- **Access, not obscurity.** Mandatory TLS/HSTS (behind a reverse proxy), TOTP 2FA,
  Argon2id password hashing, secure/HttpOnly/SameSite cookies, a strict CSP, and a
  per-record `OwnsUserData` owner-scope on every controller (foreign access → 404).
- **Encrypted at rest — operational secrets only.** IMAP/SMTP passwords, backup
  passphrases and PGP/S-MIME private keys use Laravel's `encrypted` cast (AES-256-GCM
  under `APP_KEY`, never in a DB dump). User content is plaintext by design.
- **At-rest confidentiality = your infra.** Run it on LUKS full-disk encryption with
  encrypted backups. The recommended deployment is a private box behind a reverse proxy,
  not exposed to the open internet.
- **Outbound is guarded.** Every outbound host (IMAP, SMTP, geocoding, favicon, Paperless,
  webhooks) passes an SSRF guard (link-local/metadata blocked, IP-pinned, no redirects).

## Requirements

- Docker + Docker Compose (the supported path), **or** PHP 8.5, Node 24, Composer,
  PostgreSQL 18 (+pgvector) and Valkey/Redis for a manual install.
- A reverse proxy terminating TLS (Caddy/Traefik/nginx/NetBird) in front of the app port.

## Installation

```bash
git clone https://github.com/MalteKiefer/Ledgerline.git
cd Ledgerline
cp .env.docker.example .env          # then edit APP_KEY, DB, mail, TRUSTED_PROXIES, …
docker compose up -d                 # app + worker + scheduler + db + valkey
docker compose exec app php artisan migrate --force
docker compose exec app php artisan user:set-password you@example.com --admin
```

Open the app behind your TLS proxy and sign in. `user:set-password --admin` is the
mail-independent bootstrap/reset path for the first admin account.

> Prefer prebuilt images? CI publishes to `ghcr.io/maltekiefer/ledgerline:<tag>`; the box
> pulls with `./scripts/deploy-pull.sh <tag>` instead of building on the host.

## Configuration

All configuration is environment-driven. The essentials:

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel app key — encrypts operational secrets. Keep it safe; losing it loses those secrets. |
| `APP_URL` / `FORCE_HTTPS` | Public URL; force `https://` URLs when behind a plain-HTTP reverse proxy. |
| `TRUSTED_PROXIES` | Reverse-proxy CIDRs (never `*`) so the real client IP reaches the audit log. |
| `SESSION_SECURE_COOKIE` | `true` behind TLS — also enables HSTS + COOP. |
| `DB_*` | PostgreSQL connection. |
| `REDIS_*` | Valkey/Redis for cache + queues. |
| `MAIL_*` | Outgoing mail for auth notifications (also configurable in-app). |
| `FRAME_ANCESTORS` | Clickjacking policy; default `'none'`. Set to embed the app in a dashboard on a trusted LAN. |

See `.env.docker.example` for the complete, commented list.

## API

The REST API lives under `/api/v1`, authenticated with Sanctum bearer tokens
(`abilities:device`). The full, always-current contract — every route, request body,
response shape, status code and error type — is in [`openapi.yaml`](openapi.yaml). Native
mobile clients build against it; a CI check keeps it in lockstep with the routes.

## Standalone SPA (backend-independent hosting)

The web UI is a self-contained Vue 3 SPA that authenticates with a bearer token
(localStorage) and talks to the backend **purely over `/api/v1`** — no cookies,
no CSRF, no server-rendered state. It can therefore be built as a static bundle
and hosted anywhere (nginx, a CDN, object storage), with Laravel acting as a
pure JSON API. The same contract makes a later swap of Laravel for another
backend (e.g. Go) a front-end no-op.

Build the standalone bundle:

```bash
VITE_API_URL=https://api.example.com \
VITE_APP_VERSION=$(php -r "echo config('app.version');") \
npm run build:spa            # → dist/spa (static index.html + hashed assets)
```

Build-time variables:

| Variable | Meaning |
|----------|---------|
| `VITE_API_URL` | Absolute API origin. Empty = same origin as the SPA. |
| `VITE_BASE` | Public base path the SPA is served from (default `/`). |
| `VITE_APP_VERSION` | Version shown in the sidebar footer. |

Host `dist/spa` on any static server with an **SPA history fallback** (every
non-file route serves `index.html`). Example nginx:

```nginx
location / { try_files $uri $uri/ /index.html; }
```

On the API side, allow the SPA's origin for CORS:

```
CORS_ALLOWED_ORIGINS=https://app.example.com
```

Laravel keeps serving only the backend surfaces: `/api/v1`, the byte-stream
endpoints (avatars, file/gallery raw, invoice PDF — bearer or token-in-query, so
`<img>`/`<iframe>` work cross-origin), `/dav` (CardDAV/CalDAV/WebDAV),
`/.well-known/*` and `/up`. The bundled same-origin Blade entry
(`resources/views/spa.blade.php`) continues to work for a single-host deployment
— the standalone build is additive.

## Repository layout

The repo is split so the frontend is independent of the backend (they only meet
over the API):

```
frontend/   standalone Vue 3 SPA (own package.json + Vite build → dist/)
backend/    Laravel API (app, config, routes, database, lang, tests, …)
Dockerfile  multi-stage: Node builds the SPA, PHP builds the backend and serves
            the built SPA from public/  (a single image serves both)
docker-compose.yml, docker/, scripts/, .github/   deploy + CI
```

The SPA compiles its translations from `backend/lang/*.php` (the single source),
but at runtime talks only to `/api/v1` — so `frontend/` can also be built and
hosted on its own (see “Standalone SPA” above) with `backend/` as a pure API.

## Development

```bash
# Backend (Laravel API)
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve            # API on http://localhost:8000

# Frontend (Vue SPA) — in a second terminal
cd frontend
npm install
VITE_API_URL=http://localhost:8000 npm run dev   # Vite dev server
```

Quality gates (all green before a release):
- backend: `cd backend && vendor/bin/pint && vendor/bin/phpstan analyse` (level 10)
  `&& php artisan test`, plus EN/DE/RU translation-key parity.
- frontend: `cd frontend && npm run typecheck && npm run lint && npm run build && npm run test:js`.

## License

Released under the [MIT License](LICENSE). © 2026 Malte Kiefer.
