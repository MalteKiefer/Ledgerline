# Ledgerline

Ledgerline is a small, security-focused ERP application built with Laravel. It
manages customers, their contact persons, and their projects. Authentication is
delegated entirely to a Pocket-ID OIDC provider; the application stores no
passwords of its own.

The codebase follows Laravel conventions strictly and avoids unnecessary
abstractions. All assets are bundled and served locally — no external CDNs,
fonts, or trackers are used.

## Stack

Versions verified against their authoritative sources on **2026-06-30** and
pinned in `composer.json` / `package.json`:

| Component        | Version        | Notes                                            |
| ---------------- | -------------- | ------------------------------------------------ |
| PHP              | 8.5.x          | `declare(strict_types=1)`, full type hints       |
| Laravel          | 13.x           | Latest stable framework                          |
| PostgreSQL       | 18.x           | Sole database                                    |
| Valkey           | 9.x            | Cache, session and queue store (Redis-compatible)|
| Composer         | 2.10.x         |                                                  |
| Node.js          | 22.x LTS       | For Vite asset builds                            |
| Vite             | 8.x            | Asset bundler                                    |
| Tailwind CSS     | 4.x            | CSS-first configuration                          |
| Alpine.js        | 3.x            | Added with the contact-function autocomplete     |
| laravel/socialite| 5.x            | OAuth2 client                                    |
| socialiteproviders/pocketid | 5.x | Pocket-ID OIDC provider                          |

### Why Valkey, not Redis

Valkey is the open-source fork of Redis and is wire-compatible with the Redis
protocol. Laravel's Redis driver connects to it unchanged. We use the pure-PHP
`predis` client (`REDIS_CLIENT=predis`), so the `phpredis` C extension is not
required.

## Requirements

- PHP 8.5 with the `pdo_pgsql` extension
- Composer 2.10+
- Node.js 22 LTS and npm
- A running PostgreSQL 18 server
- A running Valkey 9 server
- Access to a Pocket-ID instance (OIDC provider)

## Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Create the environment file and generate the application key
cp .env.example .env
php artisan key:generate

# 3. Configure the database and Valkey connections in .env
#    (DB_*, REDIS_*) and the Pocket-ID credentials (POCKETID_*).

# 4. Create the PostgreSQL database and role, e.g.:
#    CREATE ROLE ledgerline LOGIN PASSWORD '...';
#    CREATE DATABASE ledgerline OWNER ledgerline;

# 5. Run migrations
php artisan migrate

# 6. Build assets (or run the dev server)
npm run build      # production build
npm run dev        # development with HMR

# 7. Serve the application
php artisan serve
```

The application is then available at the `APP_URL` (default
`http://localhost:8000`).

## Environment variables

| Variable               | Purpose                                                        |
| ---------------------- | -------------------------------------------------------------- |
| `DB_CONNECTION`        | Must be `pgsql`.                                                |
| `DB_HOST` / `DB_PORT`  | PostgreSQL host and port (default `127.0.0.1:5432`).           |
| `DB_DATABASE`          | Database name (`ledgerline`).                                  |
| `DB_USERNAME` / `DB_PASSWORD` | PostgreSQL credentials.                                 |
| `REDIS_CLIENT`         | Must be `predis` (pure PHP, no extension needed).             |
| `REDIS_HOST` / `REDIS_PORT` | Valkey host and port (default `127.0.0.1:6379`).         |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | All set to `redis` → Valkey. |
| `POCKETID_BASE_URL`    | OIDC issuer base URL of your Pocket-ID instance.              |
| `POCKETID_CLIENT_ID` / `POCKETID_CLIENT_SECRET` | OIDC client credentials.            |
| `POCKETID_REDIRECT_URI`| Must match a redirect URI registered in Pocket-ID; points at `/auth/callback`. |
| `POCKETID_USE_PKCE`    | Enables PKCE for the authorization-code flow (default `true`).|

## Authentication

All sign-in goes through Pocket-ID:

1. The user visits `/login` and clicks "Continue with Pocket-ID".
2. `/auth/redirect` sends the user to Pocket-ID via Socialite (PKCE-protected).
3. Pocket-ID returns the user to `/auth/callback`, where the account is matched
   on its stable OIDC subject (`sub`) and a local session is started.
4. `/logout` invalidates the session.

Register an OIDC client in Pocket-ID with the redirect URI set to
`<APP_URL>/auth/callback` and copy the client ID/secret into `.env`.

## Deployment to Laravel Cloud

The application is built with Laravel conventions only and runs on
[Laravel Cloud](https://cloud.laravel.com) without code changes. The checklist
below covers what must be configured there; nothing in this list requires
editing application code.

### 1. Provision managed services

- **PostgreSQL** — create a Postgres database in the Laravel Cloud project.
  Cloud injects the connection details; map them to `DB_*` (see below).
- **Key–Value store (Valkey)** — create a Laravel Cloud KV store. It is
  Valkey-based and speaks the Redis protocol, so it backs the cache, session
  and queue stores exactly as the local Valkey does.

### 2. Environment variables

Set these in the Laravel Cloud environment settings (not committed to the repo):

```dotenv
APP_NAME=Ledgerline
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # generate once: `php artisan key:generate --show`
APP_URL=https://your-app.laravel.cloud

# PostgreSQL — use the values from the provisioned Cloud database.
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

# Valkey (Cloud KV) — drives cache, sessions and queues.
REDIS_CLIENT=phpredis     # phpredis is available on Cloud; predis also works
REDIS_HOST=...
REDIS_PORT=6379
REDIS_PASSWORD=...
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Pocket-ID OIDC client (must use HTTPS in production).
POCKETID_BASE_URL=https://id.your-domain.com
POCKETID_CLIENT_ID=...
POCKETID_CLIENT_SECRET=...
POCKETID_REDIRECT_URI=https://your-app.laravel.cloud/auth/callback
POCKETID_USE_PKCE=true
```

Notes:

- `APP_DEBUG` must be `false` in production so stack traces are never exposed.
- On Cloud the `phpredis` C extension is present, so `REDIS_CLIENT=phpredis` is
  the faster default; `predis` (used locally) remains a valid fallback.
- Cloud terminates TLS at its proxy. Laravel 11+ trusts the Cloud proxy
  out of the box, so HTTPS URLs and secure cookies are generated correctly.

### 3. Build & deploy commands

Laravel Cloud auto-detects the build, but ensure the pipeline runs:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

And add a deploy/release step that runs the migrations:

```bash
php artisan migrate --force
```

Optionally cache configuration and routes for performance:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Pocket-ID configuration

In the Pocket-ID admin UI, register (or update) the OIDC client so its redirect
URI exactly matches `https://your-app.laravel.cloud/auth/callback`. Mismatched
redirect URIs are rejected by the provider.

### 5. Background work

There is currently no queued work or scheduled task, so no worker or scheduler
needs to be enabled. When queues are introduced later, add a Laravel Cloud
worker pointed at the Valkey-backed `redis` queue connection.

## Security notes

- **No local passwords.** Identity is owned by Pocket-ID; the `password` column
  is nullable and unused for login.
- **PKCE** hardens the OAuth2 authorization-code exchange against interception.
- **CSRF protection** is enabled on all state-changing routes (Laravel default).
- **Mass-assignment protection** via explicit `$fillable` on every model.
- **Authorization** is enforced through Laravel Policies on every domain model.
- **Validation** of all input happens in dedicated Form Requests.
- **Parameterized queries only** — Eloquent and the query builder; no raw SQL.
- **Sessions** are stored server-side in Valkey.
- **No third-party assets or telemetry** — everything is self-hosted.

## Development workflow

The project follows the Git Flow branching model:

- `main` — production-ready; every commit is a release, tagged `vX.Y.Z`.
- `develop` — integration branch and default working branch.
- `feature/*` — branched from and merged back into `develop`.
- `release/*` — release preparation, merged into `main` and `develop`.
- `hotfix/*` — urgent fixes branched from `main`.

Branches are merged with `--no-ff` to preserve history.
