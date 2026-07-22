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
- [Deployment](#deployment-docker--host-caddy)
- [Environment variables](#environment-variables)
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
| Laravel       | 13.21          | Framework                                               |
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

## Deployment (Docker + host Caddy)

Production runs as a Docker Compose stack; TLS + routing are handled by **Caddy
on the host**, reverse-proxying to `127.0.0.1:${APP_PORT}`.

```bash
cp .env.docker.example .env      # fill APP_KEY, DB/REDIS passwords, POCKETID_*, S3
docker compose build
docker compose up -d             # app + worker + scheduler + db + valkey
docker compose --profile ml up -d       # optionally add the ML sidecar
docker compose --profile geocode up -d  # optionally add self-hosted Photon geocoding
```

Services: `app` (nginx + php-fpm, runs migrations on start), `worker`
(`queue:work`, scale with `--scale worker=N`), `scheduler` (`schedule:work`),
`db` (pgvector/pg17), `valkey`, plus the optional `ml` and `photon` sidecars.
All images are digest-pinned; every service runs non-root with
`no-new-privileges` and `cap_drop: [ALL]` (selective re-add). The app port binds
to `127.0.0.1` only — put Caddy in front for TLS 1.3 + HSTS.

Health check: `curl -fsS https://<your-domain>/up` → `200`.

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

## Environment variables

| Variable | Purpose |
| --- | --- |
| `APP_KEY` | Laravel app key (`php artisan key:generate --show`). Encrypts sessions + a few server-side non-content values; never the user vault. |
| `APP_URL` | Public URL; must be HTTPS in production. |
| `DB_*` | PostgreSQL (pgvector) connection. |
| `REDIS_CLIENT` | `predis` (pure PHP) — no `phpredis` extension needed. |
| `REDIS_HOST` / `REDIS_PORT` / `REDIS_PASSWORD` | Valkey connection. |
| `CACHE_STORE` / `SESSION_DRIVER` / `QUEUE_CONNECTION` | All `redis` → Valkey. |
| `POCKETID_BASE_URL` / `POCKETID_CLIENT_ID` / `POCKETID_CLIENT_SECRET` / `POCKETID_REDIRECT_URI` | Pocket-ID OIDC client. Redirect URI = `<APP_URL>/auth/callback`. |
| `POCKETID_ADMIN_GROUP` | OIDC group whose members may change global/infra settings (fail-closed in multi-user). |
| `AWS_*` / `FILES_S3_*` | The `files` blob disk (Hetzner/S3/R2/B2/MinIO). Bucket is private; app streams all bytes behind auth. |
| `AWS_EC2_METADATA_DISABLED` | `true` — always pass explicit S3 keys; skip the IMDS probe. |
| `TRUSTED_PROXIES` | The private ranges the host reverse-proxy uses. **Never `*`** — that lets a remote client forge `X-Forwarded-For`. |
| `ML_ENABLED` / `ML_URL` / `FACE_ENABLED` / `ML_FACE_MODEL` / `ML_CLIP_MODEL` | Machine-learning sidecar (optional). `ML_URL` defaults to `http://ml:3003`. |
| `SANCTUM_EXPIRATION` | Absolute device-token lifetime in minutes (default 180 days). |
| `DEVICE_IDLE_DAYS` | Revoke a device token unused this many days (default 90; 0 disables). |
| `DEVICE_WIPE_GRACE_MINUTES` | Grace before a remotely-wiped token is hard-revoked (default 15). |
| `PAIRING_MAX_DEVICES` | Max paired devices (app + CLI + extension) per user (default 3). |
| `BACKUP_RECONCILE_HOURS` | Full list-and-prune vs. fast incremental delta cadence (default 24). |
| `OPS_METRICS_TOKEN` | Bearer for the Prometheus `/metrics` endpoint. Unset → `/metrics` returns 404. |
| `VAULT_REMEMBER_DAYS` / `VAULT_PUBLIC_IDLE_MINUTES` | Trusted-device vault-unlock persistence vs. public-computer idle lock. |
| `HASH_DRIVER` / `ARGON_MEMORY` / `ARGON_TIME` / `ARGON_THREADS` | Server-side password hashing (Argon2id; only the public-share password gate uses it). |

See `.env.example` (local) and `.env.docker.example` (Docker) for the full set.

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
(OpenAPI 3.1, 88 operations, verified 1:1 against the route table).

---

## Development workflow

- **Git Flow.** `develop` is the working branch; every `main` commit is a tagged
  `vX.Y.Z` release (app + extension carry the same version). Merge with `--no-ff`.
- **Gates (all green before a release):** Pint, PHPStan level 10, ESLint, Vitest,
  the full PHP test suite, EN/DE language parity, a zero-knowledge scan (no new
  plaintext columns / server render paths), `openapi.yaml` in sync, and `CLAUDE.md`
  + the security register updated in the same commit.
- **Tests:** `php artisan test --teamcity`. Run `PhotoEditTest` in a filtered chunk
  — it can segfault under imagick/GD and mask later tests.
- **Conventions:** monochrome icons via `<x-icon>` only; EN/DE parity for every
  string; no AI references in code, comments, commits or releases; assets bundled
  locally (no CDNs/telemetry); only `README.md` + `CLAUDE.md` are Markdown.

---

## License

See the repository for licensing terms.
