<div align="center">

# Ledgerline

**A self-hosted, plaintext-relational personal cloud.**
Invoicing, files, contacts, calendar, gallery and a full mail archive — one app you run on your own box.

[![License: MIT](https://img.shields.io/badge/License-MIT-2ea44f.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777bb4.svg?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-ff2d20.svg?logo=laravel&logoColor=white)](https://laravel.com/)
[![Vue 3](https://img.shields.io/badge/Vue-3-42b883.svg?logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-4169e1.svg?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Self-hosted](https://img.shields.io/badge/self--hosted-Docker-2496ed.svg?logo=docker&logoColor=white)](#deployment)

</div>

---

## What is Ledgerline?

Ledgerline replaces a handful of separate self-hosted services with one coherent,
owner-scoped tool. It is **plaintext-relational**: every module is an ordinary
relational table (one row per record), so your data stays queryable, searchable,
joinable and easy to back up — no opaque blobs, no client-side crypto to lock you out
of your own data.

Confidentiality at rest is an **infrastructure** concern (full-disk encryption +
encrypted backups), not application paranoia. What the app enforces hard is the
**access** boundary: TLS/HSTS, mandatory two-factor auth, a strict CSP, per-record
owner-scope on every endpoint, and encrypted-at-rest **operational secrets** only
(SMTP/IMAP passwords, backup passphrases, PGP/S-MIME private keys — never in a DB dump).

## Modules

| Module | What it does |
|---|---|
| **Finance** | Invoices (GoBD numbering, ZUGFeRD/Factur-X e-invoice, PDF + email), payment methods, bank-statement import (MT940/CSV) with receipt matching, standalone receipts, projects, business partners, VAT-return / EÜR reports, duplicate detection, category suggestions. |
| **Files** | Nested folders + files, whole & chunked upload, streamed download, version history, trash, tags/labels/notes/favorites, server thumbnails, full-text + OCR search, ZIP/bulk, sharing (cross-user + public links), external S3/SFTP mounts, WebDAV. |
| **Contacts** | vCard 4.0 address books, Ledgerline-first CardDAV replicas (Google OAuth, iCloud and generic servers) with immutable recovery versions and delete protection, VCF import/export, photo crop, duplicate detection + merge, birthday feed, sharing. |
| **Calendar** | Events with recurrence, timezones + reminders, CalDAV sync, ICS import/export, holiday calendars, free/busy + slot finder, iMIP invitations/RSVP. |
| **Gallery** | Photos + videos, EXIF timeline + map, albums, live/motion photos, server thumbnails, optional CLIP semantic search + face recognition, sharing. |
| **Notes** | Markdown notes, folders + tags, wikilinks/backlinks, attachments, full-text search. |
| **Mail archive** | Pull-only IMAP archival to plaintext `.eml`, full-text search, sandboxed HTML reader, attachments, threading/labels/rules, `.eml`/`.mbox`/ZIP export, server-side PGP/S-MIME reading, SMTP send/reply/forward with a rich, autosaved composer, account signatures, delivery controls and attachments from device, Files or Gallery. |

Plus infrastructure (not a "module"): first-party auth (Laravel Fortify — email +
password + TOTP 2FA + WebAuthn/passkeys), multi-user admin & groups, an admin security
portal (request log + IP/user blocking), backups (S3/B2/SFTP/WebDAV, GFS rotation,
restore), Paperless integration, notifications (SMTP/ntfy/webhook + per-device push),
device pairing for mobile, and a company/invoice profile.

## Architecture — frontend and backend are separate

The repository is physically split so the two halves only ever meet over the API:

```
frontend/    standalone Vue 3 SPA (own package.json + Vite build → dist/)
backend/     Laravel API (app, config, routes, database, lang, tests, …)
Dockerfile   multi-stage: Node builds the SPA, PHP builds the backend and serves
             the built SPA from public/  (one image serves both)
docker-compose.yml, docker/, scripts/, .github/   deploy + CI
openapi.yaml the API contract — the single boundary between the two halves
```

The boundary is **`/api/v1`** and nothing else:

- **Bearer tokens only.** The SPA authenticates with a Sanctum token in
  `localStorage` and sends `Authorization: Bearer …`. There are **no cookies, no CSRF,
  no server-rendered session state** — every request is stateless.
- **A configurable API origin.** The SPA reads `VITE_API_URL` at build time; every
  request (including raw blob downloads and `<img>`/`<iframe>` stream URLs) goes through
  the API client, so the frontend can live on the same origin as the backend *or* on a
  completely different host.
- **The contract is `openapi.yaml`.** Every route, request/response shape, status code
  and error type is documented there and kept in lockstep with the routes by CI.

Because the frontend depends on the backend **only through this HTTP contract**, the
Laravel backend is replaceable. A future **Go or Python** implementation of the same
`/api/v1` surface (bearer auth, same JSON shapes, same byte-stream endpoints) is a
drop-in swap — the frontend changes nothing but `VITE_API_URL`. `openapi.yaml` is the
spec to implement against; `backend/lang/*.php` remains the translation source the SPA
compiles from at build time.

The only build-time coupling is that the SPA compiles its i18n from `backend/lang/*.php`
(the single translation source). A non-PHP backend can either keep those PHP files as
data or export them to JSON — nothing at runtime depends on Laravel.

## Reachability — web + mobile via NetBird (`home.pinlo.me`)

The production box is **not exposed to the public internet directly**. It is reachable
over a **NetBird overlay** (WireGuard mesh):

```
browser / mobile app ──TLS──▶  Caddy (NetBird edge)  ──HTTP──▶  backend :8300 (overlay IP)
        https://home.pinlo.me                                   (port not publicly routed)
```

- **Caddy** terminates TLS at the edge and reverse-proxies to the app container on the
  NetBird overlay (`http://<overlay-ip>:8300`, host header passed through). The app port
  is never routed publicly — only the overlay reaches it.
- The backend runs with **`FORCE_HTTPS=true`** (so all generated URLs are `https://`
  behind the plain-HTTP proxy hop), **`SESSION_SECURE_COOKIE=true`** (enables HSTS +
  COOP), and **`TRUSTED_PROXIES`** set to the NetBird/CGNAT ranges (never `*`) so the
  real client IP reaches the audit log.
- **Mobile apps** join the same NetBird network and talk to **`https://home.pinlo.me/api/v1`**
  with a **device bearer token** (issued via QR/CLI pairing, subject to the shared device
  cap). Native HTTP clients are not CORS-restricted, so they need nothing beyond the token
  and network reachability. Push works via a per-device UnifiedPush endpoint.
- **Browsers** load the SPA from the same origin (`https://home.pinlo.me`), so no CORS is
  involved in the default single-image deployment.

If you instead host the SPA on a *different* origin than the API (see
[Standalone frontend](#standalone-frontend-different-origin)), set
`CORS_ALLOWED_ORIGINS` on the backend to that origin — browser calls then pass CORS while
the bearer-token model is unchanged.

## Deployment

The production image is built by CI and pulled onto the box (no on-box builds).

**One image, both halves (default).** The multi-stage `Dockerfile`:

1. **assets stage (Node):** builds `frontend/` → `frontend/dist` (and self-hosts the
   tesseract OCR worker), stamping the version from the `APP_VERSION` build-arg.
2. **runtime stage (PHP + nginx, serversideup base):** installs the backend, then copies
   `frontend/dist` into `public/`. nginx serves the SPA statically; unknown routes fall
   through to Laravel, which streams the same `index.html`. `/api/v1`, `/dav`, `/up` and
   the byte-stream endpoints are served by PHP-FPM.

```bash
# On the box (pulls the CI-built GHCR image, recreates app/worker/scheduler):
git fetch --tags && git reset --hard vX.Y.Z
./scripts/deploy-pull.sh vX.Y.Z
```

`docker-compose.yml`, the deploy `.env` (compose env-file with the image tag, DB, app
config) and `scripts/` stay at the repo root; migrations run on app start. The `db`
(PostgreSQL 18 + pgvector), `valkey` (cache/queues) and optional `ml`/`maps`/`agent`
profiles are defined in compose.

### Standalone frontend (different origin)

The frontend can also be built and hosted on its own (nginx, a CDN, object storage),
with the backend as a pure API:

```bash
cd frontend
VITE_API_URL=https://api.example.com VITE_APP_VERSION=vX.Y.Z npm run build   # → dist/
```

Serve `dist/` with an SPA history fallback (`try_files $uri /index.html`) and set
`CORS_ALLOWED_ORIGINS=https://app.example.com` on the backend. The backend then only ever
serves `/api/v1`, `/dav`, `/up` and the byte streams.

## Development

```bash
# Backend (Laravel API)
cd backend
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan serve                                   # API on http://localhost:8000

# Frontend (Vue SPA) — second terminal
cd frontend
npm install
VITE_API_URL=http://localhost:8000 npm run dev      # Vite dev server
```

Quality gates (all green before a release):

- **backend:** `cd backend && vendor/bin/pint && vendor/bin/phpstan analyse` (level 10)
  `&& php artisan test`, plus EN/DE/RU translation-key parity.
- **frontend:** `cd frontend && npm run typecheck && npm run lint && npm run build && npm run test:js`.

## Configuration

Environment-driven. The essentials:

| Variable | Purpose |
|---|---|
| `APP_KEY` | Laravel app key — encrypts operational secrets. Losing it loses those secrets. |
| `APP_URL` / `FORCE_HTTPS` | Public URL; force `https://` URLs behind a plain-HTTP reverse proxy. |
| `TRUSTED_PROXIES` | Reverse-proxy CIDRs (never `*`) so the real client IP reaches the audit log. |
| `SESSION_SECURE_COOKIE` | `true` behind TLS — also enables HSTS + COOP. |
| `CORS_ALLOWED_ORIGINS` | Allowed browser origins for a split-host SPA (default `*`). |
| `DB_*` / `REDIS_*` | PostgreSQL + Valkey. |
| `MAIL_*` | Outgoing mail for auth notifications (also configurable in-app). |
| **Frontend build** | `VITE_API_URL` (API origin, empty = same origin), `VITE_BASE` (base path), `VITE_APP_VERSION` (sidebar version). |

See `.env.docker.example` (deploy) and `backend/.env.example` (local backend) for the
complete, commented lists.

## API

The REST API lives under `/api/v1`, authenticated with Sanctum bearer tokens
(`abilities:device`). The full, always-current contract is in
[`openapi.yaml`](openapi.yaml); a CI check keeps it in lockstep with the routes. This is
the interface a Go/Python backend would implement and native mobile clients build
against.

## License

MIT — see [`LICENSE`](LICENSE).
