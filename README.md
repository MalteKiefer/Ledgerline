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

## Teams & data isolation

Data is owned by **teams**, which mirror **Pocket-ID groups**. The application
requests the `groups` scope at login and, on each sign-in, maps every group to
a team (key `group:<id>`) and syncs the user's memberships. A user with no
groups gets a private personal team (`user:<id>`).

Every owned record (customers and their contacts, branches and projects) carries
a `team_id`. A global Eloquent scope restricts **all** queries — including
route-model binding, dashboard counts and global search — to the current user's
teams, so a user can never see or reach another team's data; an out-of-team
record simply returns 404. Users in multiple teams pick an **active team** (in
the header) which owns newly created records.

To make this work, configure the Pocket-ID OIDC client to include group
membership in the `groups` claim.

### Reassigning existing data to a group

The teams migration parks any pre-existing records in a **Default Team**. Since
a real user is synced to their Pocket-ID group teams on login, that data will
not be visible until it is moved into the right team. Use the console:

```bash
php artisan teams:list                              # find team ids/keys + counts
php artisan teams:reassign default group:engineering --with-members
```

`teams:reassign` moves all customers, contacts, branches, projects and files
from the source team to the target (by id or key); `--with-members` also adds
the source team's members to the target.

## File storage

Files can be attached to customers and projects and are listed team-wide under
**Files** in the menu. They are stored on a private, S3-compatible object store
(the `files` disk): **MinIO** locally, **Cloudflare R2 / S3** in production.

- Uploads stream through the app, which detects the file type from its content
  and, for unencrypted text-extractable files, captures searchable text.
- Downloads always stream through the app behind team authorization; the bucket
  is never public and no object ACLs are set (R2 rejects ACLs).
- Files are tagged and included in global search (by name, type, tags, and
  extracted content when unencrypted).

Local development uses `FILES_S3_*` (see `.env.example`) pointed at MinIO:

```bash
# Start MinIO and create the bucket (root creds match FILES_S3_KEY/SECRET):
MINIO_ROOT_USER=ledgerline MINIO_ROOT_PASSWORD=ledgerline-secret \
  minio server ~/ledgerline-minio-data --address 127.0.0.1:9000 &
mc alias set local http://127.0.0.1:9000 ledgerline ledgerline-secret
mc mb --ignore-existing local/ledgerline-files
```

In production the `files` disk falls back to the standard `AWS_*` variables, so
a Laravel Cloud R2 bucket works by setting only `AWS_*` (with
`AWS_DEFAULT_REGION=auto`) — leave `FILES_S3_*` unset.

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

# File storage bucket (Laravel Cloud provides these for a Cloudflare R2 bucket).
# The "files" disk falls back to these AWS_* variables, so no FILES_S3_* are
# needed in the cloud — just leave them unset.
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto                 # R2 uses "auto"
AWS_BUCKET=...
AWS_ENDPOINT=https://<account>.eu.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=false
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
