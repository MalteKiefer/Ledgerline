# Ledgerline — Projekt-, Anforderungs- & Session-Kontext

Self-hosted **zero-knowledge personal cloud** (Laravel). Server hält NUR Ciphertext:
alles wird im Browser ver-/entschlüsselt. Selbst der Server-Betreiber kann Daten
nicht lesen. Single-tenant Server, aber code-seitig **voll Multi-User-isoliert**.

Module: **Galerie, Dateien, Notizen, Todos, Lesezeichen, Passwörter, Kontakte,
Rechnungen, Backup, Paperless**. Version **v1.484.0** (live https://home.kiefer-networks.de, `/up`=200).

> Sharing lebt wieder (ZK): **öffentliche Links** `/s/{token}` für **Galerie-Album, Datei UND Ordner** — Share-Key im URL-Fragment (nie an Server), optionales Passwort = rate-limitiertes Gate, optionaler Download, Expiry. `PublicShare`-Modell (`kind` = gallery_album|file|folder), owner-CRUD via Trait `Concerns\ManagesPublicShares` (genutzt von `GalleryShareController` + `FileShareController`), `PublicShareController` (public; Blob-Route wählt Disk-Prefix+Ledger per kind: gallery/GalleryBlob vs files/FileBlob), `ShareCrypto` in vault.js, Viewer-Komponenten `publicShare`/`fileShare`. Die alte „script-less CSP für Public-Share-Seiten"-Regel gilt hier NICHT: die ZK-Ansichtsseiten brauchen das gebündelte JS (globale CSP `script-src 'self'` reicht, kein Inline).

> **Achtung — großer Pivot seit dem alten Stand (v1.298).** Die App wurde
> zwischen 2026-07-06 und 2026-07-17 komplett von *plaintext* auf
> *zero-knowledge* (client-verschlüsselt) umgebaut. **Mail, Kalender/CalDAV und
> Kontakte-CardDAV sind ENTFERNT** (mit ZK inkompatibel). Frühere Doku, die
> „nichts verschlüsselt außer Passwörter" oder Mail/DAV-Suite beschreibt, ist
> obsolet. Aktueller Ground-Truth = `README.md` + Code, nicht die alte Historie.

`README.md` ist die maßgebliche, gepflegte Beschreibung. Diese Datei = Arbeits-
kontext + stehende Anweisungen. Detail-Historie in
`~/.claude/projects/-Users-malte-kiefer-Entwicklung/memory/`.

---

## Stack
- Laravel 13 / PHP 8.4 (`declare(strict_types=1)`), PostgreSQL 17 **pgvector** (prod) / sqlite (tests), Valkey 8 (predis, kein phpredis).
- Frontend: **Alpine.js in EINER Datei** `resources/js/app.js` + Blade + Tailwind 4 + Vite 8. Kein externes CDN — alles via npm/Vite gebündelt.
- **Client-Crypto** `resources/js/vault.js`: Passphrase --Argon2id--> KEK --unwrap--> Vault Key (VK). Sealing via libsodium-wrappers-sumo (XChaCha20-Poly1305). Wrap der VK auf Trusted-Device via AES-GCM (WebCrypto, non-extractable, in IndexedDB). Entschlüsselung schwerer Blobs im Worker-Pool `resources/js/decrypt.worker.js`.
- Auth: Pocket-ID OIDC (Socialite `pocketid`, PKCE), Match auf stabiler `sub`. Keine App-Passwörter. `groups`-Claim → Admin-Gate.
- Sanctum 4 Bearer-Tokens für Mobile/CLI-API (`/api/v1`). sabre/dav v4 NUR noch für Files-over-WebDAV + WebDAV-Backup-Ziel (kein CalDAV/CardDAV mehr). Bilder: intervention/image v4 + imagick. ML: immich-machine-learning Sidecar (profile-gated, optional).

---

## STEHENDE ANWEISUNGEN DES USERS (dauerhaft gültig)
- **Gründlichkeit/Parallelität:** große Aufgaben (Audits, Redesign, Feature-Batches) mit **min. 45 parallelen Agenten/Workflows**. Vollständige Analyse aller betroffenen Module, nicht oberflächlich.
- **„Sei hart, keine Kompromisse"** bei Audits/Fixes: ALLE Findings adressieren, strikt RFC/POSIX/PHP-/Laravel-Best-Practice. Immer zusätzlich Scan auf toten/ungenutzten/doppelten/nicht-generalisierten Code → so viel wie möglich generalisieren.
- **„Mache alle weiteren Schritte ohne Nachfragen" / „setze alles um":** eigenständig bis Ende inkl. Release+Deploy. Anhalten NUR bei: (a) Deploy-Freigabe (Klassifikator blockt Prod-Deploy immer), (b) Artefakten die nur der User hat (Screenshots, Fehler-Traces), (c) echten irreversiblen Entscheidungen.
- **Kommunikation:** knapp, technisch, deutsch, handlungsorientiert, keine Floskeln.
- Jede Änderung = **eigenes getestetes Git-Flow-Release + Deploy** (nicht sammeln, außer User sagt es).

## Feste Konventionen (nicht verletzen)
- **Monochrome Icons** nur via `<x-icon name="...">` (heroicons-outline, `currentColor`). Kein emoji, keine Farbe. Toggle-States = outline vs `-solid`, nicht Farbe. Unbekannter Name → **leeres SVG (unsichtbar)**, nie Fehler → neue Icons als Pfad in `resources/views/components/icon.blade.php`.
- Geteilte UI-Komponenten: `x-button`, `x-page-heading` (:title/:subtitle + `<x-slot:actions>`), `x-icon`, `x-sheet` (Off-canvas), `x-nav`/`x-mobile-nav`, `x-notification-panel`. Keine Einzel-Neubauten.
- **EN/DE-Parität**: jede Lang-Änderung in `lang/en/*` UND `lang/de/*`, identische Keys. Lang ist **namespaced PHP** (`messages.php`, `gallery.php`, `files.php`, `contacts.php`, `invoices.php`, `vault.php`, `shares.php`, …). Der `lang-key-existence-preflight`-Hook meldet verschachtelte Keys fälschlich als fehlend — mit PHP verifizieren.
- **Keine AI-Referenzen** in Code/Kommentaren/Commits/PRs/Releases (kein Claude/Anthropic/Copilot/„generated with AI", kein `Co-Authored-By`). Commits: englisch, imperativer Betreff ≤72 Zeichen + Body. `git commit --no-verify`. Repo-Git-Email: `malte.kiefer@kiefer-networks.de`.
- Nur `README.md`/`CLAUDE.md` als Markdown im Repo.
- **Zero-Knowledge-Regel:** Server darf NIE Klartext-Inhalt sehen oder ableiten. Neue Felder = versiegelt im Manifest, nicht als eigene Spalte. Alle Metadaten padden (Padmé). Kein Server-Rendering von Inhalt (Markdown/Thumbnails werden client-seitig erzeugt).
- Modul-Muster: opaque store + Alpine-Client. Controller liefern sealed Blobs + Manifest + Version + Timestamp, sonst nichts.

## Layout-/UI-Entscheidungen (getroffen, gelten weiter)
- **Mobile ≠ Desktop**, bewusst getrennt.
- **Mobile Navigation = Bottom-Tab-Bar** (`x-mobile-nav`) — reaktiviert im Redesign (die frühere „Bottom-Bar verworfen, Drawer von links"-Regel gilt NICHT mehr). Single-source Nav = `config/navigation.php` (`primary[]` = 5 meistgenutzte / Bottom-Bar-Slots, `more[]` = Desktop-Dropdown / Mobile-More-Sheet). Aktuell primary: Gallery, Files; more: Notes, Todos, Bookmarks, Contacts, Invoices.
- Modul-Sidebars: Desktop = weiße Card/Rail (`hidden md:block`), mobil = Slide-over `x-sheet side=left`. Body je Modul in `<module>/_sidebar_content.blade.php` (einmal editieren = beide Varianten). Sidebar-Card nur so hoch wie Inhalt.
- `Alpine.store('nav')` {navOpen, sidebarOpen, toggleNav, toggleSidebar, closeAll}. `<body>` trägt `x-data` (sonst binden Nav/Drawer nicht).
- Hover-Row-Actions touch-fest: `flex md:hidden md:group-hover:flex`.

## Alpine-Gotchas
- `<template x-if>` braucht genau 1 Root-Element. Objekt-`:style` statt String-`:style` (String überschreibt `x-show`'s display:none).
- Kein nackter JS-Kommentar als Alpine-Ausdruck (`@paste="/* … */"` → SyntaxError, killt Komponente).
- Closure-Argumente von `Alpine.data(...)` sind in Blade-Template-Ausdrücken (`x-text`/`:bind`) NICHT sichtbar → für Template-Strings `{{ __('...') }}` statisch oder `@js(__('...'))`.
- Alpine-Komponenten (Stand v1.480): `vaultGallery`, `vaultFiles`, `notes`, `todos`, `bookmarks`, `contacts`, `invoices`, `backupRuns`, `devicePairing`, `paperlessSettings`, `notificationBell`, `cropModal`, `toastHub`.

---

## ZERO-KNOWLEDGE-ARCHITEKTUR (Kern)
- **Key-Hierarchie** (`vault.js`): Passphrase → Argon2id → KEK; KEK unwrapped die per-user **Vault Key (VK)**. VK verlässt den Browser nie. Recovery-Key als zweiter Wrap. Trusted-Device: VK mit non-extractable AES-GCM-Key in IndexedDB gewrapped (`VAULT_REMEMBER_DAYS`); Public-Computer: session-only + kurzer Idle-Lock (`VAULT_PUBLIC_IDLE_MINUTES`).
- **Opaque Store:** jedes Modul (Notes/Todos/Bookmarks/Files/Contacts/Invoices) liegt als Ciphertext in EINEM sealed Workspace-Manifest → `VaultStore` (`vault_store`, eine Zeile/User: sealed manifest + version + timestamp, optimistic lock). Galerie hat EIGENES sharded sealed Manifest (`GalleryStore`) wegen Skalierung.
- **Blob-Ledger + Modelle:** `GalleryBlob`, `FileBlob`, `ContactBlob` (+ `GalleryStore`, `VaultStore`). Bytes sind bereits client-Ciphertext, Server streamt roh. Blobs zu Padmé-Buckets gepaddt (Größe verrät nichts), Manifest Padmé mit 4 KiB-Floor.
- **`Support\BlobStore`** = generalisierter Blob-Zugriff (statt Storage::disk-Literale). Orphan-Sweeps täglich: `gallery:sweep-orphans`, `files:sweep-orphans`, `contacts:sweep-orphans`.
- **Isolation-Traits** `App\Models\Concerns\AssignsOwner` (creating-Hook stempelt owner aus `Auth::id()`, nicht fillable = unfälschbar; `scopeOwnedBy`) + `OwnsUserData` (globaler Read-Scope `where owner = Auth::id()` nur wenn `Auth::check()`; aus in Console/Queue). **Bulk-/destruktive/Export-Pfade explizit owner-scopen** (Query-Builder umgeht Eloquent-Events).
- **Admin-Gate:** OIDC-Gruppe `POCKETID_ADMIN_GROUP` → darf globale/infra Settings ändern. **Fail-closed bei Multi-User** wenn Gruppe leer; Single-User erlaubt.
- **DB-Dump = sensibel:** enthält sealed rows + wrapped VK-Material → Backup-Archive **immer** force-verschlüsselt (Argon2id SENSITIVE, versioniertes Container-Format).

## SICHERHEITS-POSTURE
- **Zero-Knowledge at rest** — Server hält nur Ciphertext-Blobs + sealed, größen-gepaddte Manifeste.
- **Bewusste, user-initiierte Boundary-Crossings (dokumentiert):** (1) ML-Sidecar bekommt transient-entschlüsselte Foto-Bytes für Faces/CLIP-Embeddings (nie automatisch beim Upload, opt-in). (2) Geocoding schickt Adress-Lookup an OSM Nominatim/Photon. Beide optional, beide über SSRF-Guard; self-hosted hält sie in-boundary.
- **SSRF-Guard `App\Support\OutboundUrl`**: blockt link-local/metadata (inkl. `::ffff:169.254.169.254`), IP-gepinnte PendingRequest (DNS-Rebinding dicht). Genutzt von Geocoding, ML-Sidecar, Backup-Zielen, Notification-Webhooks, Paperless.
- **Rate-Limits** flächendeckend: auth, pairing, geocoding, ML, store-writes, blob-upload (chunked init/part/complete/abort), backups, WebDAV `throttle:dav`. Array-/Manifest-Caps, Streaming-Caps.
- **Device-Token-Lifecycle:** absolute Lebensdauer (`SANCTUM_EXPIRATION`, default 180 Tage), Idle-Revoke (`DEVICE_IDLE_DAYS` 90), per-device abilities, **enforced remote wipe** (nach Self-Erase-Grace `DEVICE_WIPE_GRACE_MINUTES` hart revoked). Pairing-Cap `PAIRING_MAX_DEVICES` (default 3).
- **Headers/CSP:** eigene strikte CSP/HSTS (kein `script-src 'unsafe-inline'`, `'unsafe-eval'` für Alpine behalten); script-less CSP für Public-Share-Seiten; sandboxed iframes; nosniff.
- **Infra-Härtung:** Docker Alpine-Base (CVE-Surface 230→1), Images per Digest gepinnt, `no-new-privileges` + Drop `CAP_NET_RAW`, App-Port an `127.0.0.1` gebunden. ImageMagick `policy.xml`. `AWS_EC2_METADATA_DISABLED=true`. `TRUSTED_PROXIES` = private Ranges, **nie `*`**.
- **Observability:** in-app Error-Log + token-gated Prometheus `/metrics` (`OPS_METRICS_TOKEN`; unset → 404). `ops:alert-errors`, `ops:snapshot-storage`.

## GETEILTE ABSTRAKTIONEN (nutzen, nicht duplizieren)
`OutboundUrl` (SSRF), `Support\BlobStore`, `AssignsOwner::scopeOwnedBy`/`ownedBy()` + `OwnsUserData`, `vault.js` (client crypto core, seal/unseal), `decrypt.worker.js` (Worker-Pool, geteilt Galerie↔Files), `Support\ArchiveName` (Zip-slip-sicher), `Support\KeepBlankSecrets::preserve()`, `ChannelNotifier` (ntfy/webhook/mail), `ImageManagerFactory`, `DiskTempFile`. app.js: `apiJson()` hinter `_json`.

---

## MODUL-INVENTAR (Features, zero-knowledge)
- **Galerie** (`vaultGallery`): Fotos/Videos client-verschlüsselt; sharded sealed Manifest; HEIC/HEIF/AVIF + Apple Live/Motion Photos (Paarung HEIC+MOV beim Upload, Motion-Clip on hover). Thumbnails im Worker-Pool entschlüsselt, immutable Blob-Cache, gefensterte Grid (skaliert >1000e), memoisierte Derived-Data. Duplikate (pHash + CLIP) im Web-Worker. **People:** In-Browser Face-Clustering + manuelles Tagging trainiert Recognition, whole-library re-analyze, Merge, Link↔Kontakte. **Smart Search:** multilingual CLIP (M-CLIP `XLM-Roberta-Large-Vit-B-32`) via ML-Sidecar, client-OCR (tesseract.js). **Editing:** non-destruktiv im Viewer, Rotate-Fit 90/270. **Map:** Leaflet + self-hosted Photon/Nominatim reverse-geocode (opt-in). `GalleryController`/`GalleryBlobController`/`GalleryStoreController`/`GalleryProcessController` (analyze/embed-text/reverse). config `config/gallery.php` (ffmpeg/exiftool/ml).
- **Dateien** (`vaultFiles`): nestbarer Ordner-Browser, Versionierung + Restore, per-user Quota, Files-over-WebDAV, Backup-Integration. Blobs client-Ciphertext (keine Server-Thumbnails).
- **Notizen/Todos/Lesezeichen:** sealed records, client-gerendertes Markdown (marked + DOMPurify) für Notizen. Gemeinsamer ZK-Lifecycle-Mixin (`zkModule()`).
- **Passwörter** (`passwords`): ZK Password-Manager, Records in `LLStore.data.secrets` + Ordner in `secretFolders` (opaque store, kein Server-Storage/Migration). 6 Typen (login/password/card/wifi/license/server), **per-item Versions-Historie** bei jeder Feldänderung (+ Diff „was geändert" in der Edit-Ansicht), client-TOTP (WebCrypto HMAC-SHA1), Passwort-Generator, **WiFi-QR** (`qrcode`-Lazy-Import; QR-T: nur nopass/WEP/WPA, WPA2/3+Enterprise mappen auf WPA), Copy-mit-Auto-Clear, Reveal/Hide. **Ordner + Tags**, Login **Multi-URL** + **Custom-Fields** (secret-fähig), **Kartentyp-Erkennung** (Visa/MC/Amex/…), erweiterte WiFi-Security (bis WPA3-Enterprise). **Favicon/BIMI** server-proxied via `PasswordIconController` (SSRF-Guard, Domain transient, Ergebnis als data-URI im sealed Item — bewusster Boundary-Crossing, User-gewählt) — Fallback Letter-Avatar. Unlock = Vault-Passphrase. Dynamische Typ-Icons via `passwords/_icon.blade.php` (x-icon server-gerendert, `::name` reaktiv geht NICHT).
- **Kontakte** (`contacts`): ZK vCard 4.0 (KEIN CardDAV), client-seitiger Import/Export, verschlüsselte Avatare (`ContactBlob`), Adress-Mini-Maps, bidirektionaler Link zu Galerie-People. `contacts:sweep-orphans`.
- **Rechnungen** (`invoices`): ZK Invoices-Modul + Company-Settings, Nummern-Sequenzen/-Format, Design/Template-Settings (editorial default, 1-Seite), Import.
- **Backup** (`backupRuns`): ZK-aware inkrementell. Files/Galerie blob-by-blob gespiegelt (High-Water-Mark über Blob-Ledger; full list-and-prune reconcile alle `BACKUP_RECONCILE_HOURS`). DB-Dump immer verschlüsselt. Ziele S3/B2/SFTP/WebDAV. `backups:run-due`, `backups:decrypt` (CLI), Dry-run-Verifier.
- **Paperless-ngx** (`paperlessSettings`): server-side Token, `paperless:sync` stündlich, SSRF-guarded.
- Globale Suche + Dashboard über alle Module (client-seitig, auto-scoped).

---

## DEPLOY-RITUAL (server.p37.nexus) — pro Deploy explizite User-Freigabe (Klassifikator blockt sonst)
Remote-Shell = **fish** → in `bash -lc '…'` wrappen.
```
ssh -p 2222 -i ~/.ssh/id_priv -o StrictHostKeyChecking=no root@server.p37.nexus \
  bash -lc "'cd /srv/ledgerline && git fetch -q --tags && git checkout -q vX.Y.Z \
  && IMAGE_TAG=vX.Y.Z docker compose build app && IMAGE_TAG=vX.Y.Z docker compose up -d'"
```
- **IMMER `up -d` ohne Service-Namen** — app/worker/scheduler teilen `ledgerline:${IMAGE_TAG}`; `up -d app` ließ worker+scheduler auf altem Image. Danach `docker compose ps` prüfen: alle drei auf neuem Tag.
- Alte Images löschen (nur aktuelles + vorheriges Tag behalten): `docker images --format "{{.Repository}}:{{.Tag}}" | grep "^ledgerline:" | grep -v -E "vNEU|vVORHER" | xargs -r docker rmi`.
- **NIE `docker compose pull`** (lokal gebautes Image). Migrationen laufen automatisch beim App-Start.
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://home.kiefer-networks.de/up` → **200**.
- Infra: Debian 13, Docker; `/srv/ledgerline`, App-Port **8300** (bind 127.0.0.1, `APP_PORT`), Domain **home.kiefer-networks.de** (DNS IPv6-only). Caddy auf dem HOST (`systemctl restart caddy`, admin-API aus). Build-DNS-Quirk: compose `build.network: host`. Worker skalieren `--scale worker=N`. ML-Service (immich) im `ml`-Profil. Server ist single-user, führt KEINE Tests.

## RELEASE-RITUAL (Git Flow)
1. Auf `develop`. Version-Bump `config/app.php` (`env('APP_VERSION','X.Y.Z')`).
2. `vendor/bin/pint --dirty` → passed. `npm run build`. EN/DE-Parität. AI-Scan (grep geänderte Dateien). ZK-Scan (keine neuen Klartext-Spalten/Server-Render-Pfade).
3. Tests: `php artisan test --teamcity` (Hook erzwingt `--teamcity`). **BUG: `PhotoEditTest` segfaultet** (imagick/GD) in vollen Läufen und maskiert danach laufende Tests → in Häppchen mit `--filter='…'` laufen. „0 failures" aus vollem Lauf ist UNZUVERLÄSSIG.
4. `php artisan view:cache`. Commit. `git checkout main && git merge --no-ff develop && git tag vX.Y.Z && git push origin main develop --tags`. `gh release create`. Zurück `develop`. Deploy (s. o.).
- Hotfixes = Patch-Bump. Docker-Dateien nur auf `main`/`develop` — Tag auschecken zum Deployen.

---

## HISTORIE (Kurz)
- **v1.298 → ~1.480** (2026-07-06 bis 07-17): kompletter Umbau plaintext → zero-knowledge.
  - Vault-Kern (per-user Crypto, Argon2id/libsodium), opaque store (Notes/Todos/Bookmarks/Files → sealed Manifest), Metadata-Leak-Audits.
  - **ZK-Galerie** in Phasen: server storage (`gallery_blobs`+`gallery_store`) → blob-only + transform endpoints → client pipeline → content search + map → legacy-Purge (plaintext-Backend/Tabellen/Routes/Tests raus, altes `PhotoTransform` gelöscht).
  - Alle ML client-seitig in Web-Workers: Faces, CLIP (multilingual), OCR, Duplikate, Thumbnails.
  - Neu: ZK-Kontakte (kein CardDAV), ZK-Invoices-Modul, self-hosted Photon-Geocoding, ZK-full-text + CLIP-Suche für Dateien.
  - **Entfernt:** Mail-Suite, Kalender/CalDAV, CardDAV, alle DAV-Sharing-Pfade dafür.
  - Skalierungs-Hardening (Top of log): concurrent-blob-fetch-cap, 429-Recovery bei großen Libraries, reconcile-dedupe, store-save-coalescing, inkrementelle Backups via Blob-Ledger, Alpine-runtime-Image.

## NOCH OFFEN / BEOBACHTEN
- `PhotoEditTest`-Segfault ungelöst (nur Test-Artefakt, Deploy unberührt).
- Skalierung bei sehr großen Libraries bleibt der Hotspot (429/409-Stürme) — jüngste Commits kämpfen genau da.
- Sharing-Umfang unter ZK prüfen (lang `shares.php` + Public-Share-CSP existieren; Detail-Status im Code verifizieren bevor darauf gebaut wird).

## MEMORY & CHECKS
- Memory: `~/.claude/projects/-Users-malte-kiefer-Entwicklung/memory/` (Index `MEMORY.md`).
- Icon-Audit: `x-icon name="…"` gegen Keys in `icon.blade.php` — MISSING = unsichtbar.
- ZK-Check vor Merge: kein neuer Server-Pfad, der Klartext-Inhalt sieht/ableitet.
