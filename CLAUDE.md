# Ledgerline — Projekt-, Anforderungs-, Security- & Session-Kontext

Self-hosted **zero-knowledge personal cloud** (Laravel). Server hält NUR Ciphertext:
alles wird im Browser ver-/entschlüsselt. Selbst der Server-Betreiber kann Daten
nicht lesen. Single-tenant Server, aber code-seitig **voll Multi-User-isoliert**.

Module: **Galerie, Dateien, Notizen, Todos, Lesezeichen, Passwörter, Kontakte,
Rechnungen, Backup, Paperless**. Version **v1.500.x** (live https://home.kiefer-networks.de, `/up`=200).
Zusätzlich: **Browser-Extension** (Chromium, MV3) für ZK-Passwort-Autofill.

`README.md` ist die maßgebliche, gepflegte Feature-Beschreibung. Diese Datei =
Arbeitskontext + stehende Anweisungen + **Security-Entscheidungsprotokoll**.
Detail-Historie in `~/.claude/projects/-Users-malte-kiefer-Entwicklung-ledgerline/memory/`.

> **CLAUDE.md ist in Git und MUSS immer den aktuellen Stand widerspiegeln.**
> Nach JEDER Änderung (Feature, Fix, Refactor, Dep-Upgrade, Infra, Security)
> wird CLAUDE.md im selben Release aktualisiert — Features, Konventionen,
> Versionen, und besonders **Security-Entscheidungen, Ausnahmen und Design-
> Entscheidungen** (siehe eigene Sektion unten). So ausführlich wie möglich.
>
> **VERBINDLICH: Jede Aufweichung einer Security-Kontrolle** (CSP relaxen, neue
> Permission/Scope, Validierung/Sanitizing lockern, Rate-Limit/Owner-Scope/Gate
> entfernen, Klartext-Boundary öffnen, Krypto-Parameter senken, `unsafe-*`, etc.)
> MUSS im Register „SECURITY-ENTSCHEIDUNGEN" mit **Datum + Begründung + Kompen-
> sation** eingetragen werden — im selben Commit. Keine stille Aufweichung.

---

## Stack
- Laravel 13 / PHP 8.4 (`declare(strict_types=1)`), PostgreSQL 17 **pgvector** (prod) / sqlite (tests), Valkey 8 (predis, kein phpredis).
- Frontend: **Alpine.js modularisiert (Vite-gebündelt)** — `resources/js/app.js` (~753 Z., Bootstrap + Store-/Komponenten-Registrierungen) + `resources/js/shared/*` (Utilities) + `resources/js/components/*` (ein Modul pro Alpine.data-Komponente) + Blade + Tailwind 4 + Vite 8. Kein externes CDN. **Konvention „Alpine in EINER Datei" ist aufgehoben** (Refactor abgeschlossen, s. REFACTORING). `npm run lint` (eslint `no-undef`) MUSS vor jedem Commit an resources/js grün sein — fängt fehlende Imports, die der Bundler NICHT meldet.
- **Client-Crypto** `resources/js/vault.js`: Passphrase --Argon2id(ops=4/mem=256MiB)--> KEK --unwrap--> Vault Key (VK). Sealing via libsodium-wrappers-sumo (XChaCha20-Poly1305 secretbox). Wrap der VK auf Trusted-Device via AES-256-GCM (WebCrypto, non-extractable, in IndexedDB). Entschlüsselung schwerer Blobs im Worker-Pool `resources/js/decrypt.worker.js`.
- Auth: Pocket-ID OIDC (Socialite `pocketid`, **PKCE + state**), Match auf stabiler `sub`. Keine App-Passwörter. `groups`-Claim → Admin-Gate (fail-closed bei Multi-User ohne Gruppe).
- Sanctum 4 Bearer-Tokens für Mobile/CLI/Extension-API (`/api/v1`). sabre/dav v4 NUR für Files-over-WebDAV + WebDAV-Backup-Ziel (kein CalDAV/CardDAV). Bilder: intervention/image v4 + imagick. ML: immich-machine-learning Sidecar (profile-gated, optional).
- **Dependency-Policy:** immer neueste stabile Version, per exakter Version + Digest gepinnt (Docker-Images), keine Alt-Majors aus Bequemlichkeit. CI (Dependabot + Trivy) hält das nach. Stand: PHP 8.4, Node 22 LTS, Laravel 13.20, pdfjs-dist 6.x, dompurify 3.4.12, vite 8.1.5.

---

## STEHENDE ANWEISUNGEN DES USERS (dauerhaft gültig)
- **Gründlichkeit/Parallelität:** große Aufgaben (Audits, Redesign, Feature-Batches) mit **min. 45 parallelen Agenten/Workflows**. Vollständige Analyse aller betroffenen Module, nicht oberflächlich.
- **„Sei hart, keine Kompromisse"** bei Audits/Fixes: ALLE Findings adressieren, strikt RFC/POSIX/PHP-/Laravel-Best-Practice. Immer zusätzlich Scan auf toten/ungenutzten/doppelten/nicht-generalisierten Code → so viel wie möglich generalisieren.
- **Security kompromisslos:** wo eine sichere Option Performance/Komfort/Features kostet, gewinnt Security. „Akzeptables Risiko" nur mit expliziter, schriftlicher Begründung im Security-Register. **Jede Security-Aufweichung wird in CLAUDE.md festgehalten** (Datum, Begründung, Kompensation).
- **„Mache alle weiteren Schritte ohne Nachfragen" / „setze alles um":** eigenständig bis Ende inkl. Release+Deploy. Anhalten NUR bei: (a) Deploy-Freigabe (Klassifikator blockt Prod-Deploy immer), (b) Artefakten die nur der User hat (Screenshots, Fehler-Traces), (c) echten irreversiblen Entscheidungen (z. B. destruktive Migration am Live-Vault, großes Krypto-Sharing-Design).
- **Kommunikation:** knapp, technisch, deutsch, handlungsorientiert, keine Floskeln.
- Jede Änderung = **eigenes getestetes Git-Flow-Release + Deploy** (nicht sammeln, außer User sagt es).
- **Nach jeder Änderung CLAUDE.md aktualisieren** (siehe Box oben) — inkl. Security-/Design-Entscheidungen und JEDER Security-Aufweichung.

## Feste Konventionen (nicht verletzen)
- **Monochrome Icons** nur via `<x-icon name="...">` (heroicons-outline, `currentColor`). Kein emoji, keine Farbe. Toggle-States = outline vs `-solid`, nicht Farbe. Unbekannter Name → **leeres SVG (unsichtbar)**, nie Fehler → neue Icons als Pfad in `resources/views/components/icon.blade.php`. (In der Extension inline-SVG mit gleichem heroicons-Pfad, `currentColor`.)
- Geteilte UI-Komponenten: `x-button`, `x-page-heading` (:title/:subtitle + `<x-slot:actions>`), `x-icon`, `x-sheet`, `x-nav`/`x-mobile-nav`, `x-notification-panel`. Keine Einzel-Neubauten.
- **EN/DE-Parität**: jede Lang-Änderung in `lang/en/*` UND `lang/de/*`, identische Keys. Lang ist **namespaced PHP** (`messages.php`, `gallery.php`, `files.php`, `contacts.php`, `invoices.php`, `vault.php`, `shares.php`, `passwords.php`, …). Der `lang-key-existence-preflight`-Hook meldet verschachtelte Keys fälschlich als fehlend — mit PHP verifizieren.
- **Keine AI-Referenzen** in Code/Kommentaren/Commits/PRs/Releases (kein Claude/Anthropic/Copilot/„generated with AI", kein `Co-Authored-By`). Commits: englisch, imperativer Betreff ≤72 Zeichen + Body. `git commit --no-verify`. Repo-Git-Email: `malte.kiefer@kiefer-networks.de`.
- **Nur `README.md` + `CLAUDE.md` als Markdown im Repo** (beide git-tracked). Andere `.md` gehören nicht ins Repo.
- **Zero-Knowledge-Regel:** Server darf NIE Klartext-Inhalt sehen oder ableiten. Neue Felder = versiegelt im Manifest, nicht als eigene Spalte. Alle Metadaten padden (Padmé). Kein Server-Rendering von Inhalt (Markdown/Thumbnails client-seitig).
- Modul-Muster: opaque store + Alpine-Client. Controller liefern sealed Blobs + Manifest + Version + Timestamp, sonst nichts.

## Layout-/UI-Entscheidungen (getroffen, gelten weiter)
- **Mobile ≠ Desktop**, bewusst getrennt. **Mobile Navigation = Bottom-Tab-Bar** (`x-mobile-nav`). Single-source Nav = `config/navigation.php` (`primary[]` = Bottom-Bar-Slots, `more[]` = Desktop-Dropdown / Mobile-More-Sheet).
- Modul-Sidebars: Desktop = weiße Card/Rail (`hidden md:block`), mobil = Slide-over `x-sheet side=left`. Body je Modul in `<module>/_sidebar_content.blade.php`.
- `Alpine.store('nav')` {navOpen, sidebarOpen, toggleNav, toggleSidebar, closeAll}. `<body>` trägt `x-data`.
- Hover-Row-Actions touch-fest: `flex md:hidden md:group-hover:flex`.
- **Passwortmanager-Layout (Web):** EINE zusammenhängende Fläche (`rounded-2xl`, innere Trennlinien), 3 Zonen: links Tresore+Tags(+Health/Trash), Mitte Liste (Suche + Typ-Filter daneben), rechts Inline-Detail. NICHT drei schwebende Karten. Feld-Detail im 1Password-Stil: umrahmter Feld-Container, Labels in Akzentblau. Versions-Historie = aufklappbares Akkordeon unter den Feldern (JSON-Diff pro Revision, Secrets als „(changed)" maskiert), KEIN Modal. **Die Extension** nutzt hingegen ein 1Password-artiges Master-Detail-Popup — bewusst anders als die App.

## Alpine-Gotchas
- `<template x-if>` braucht genau 1 Root-Element. Objekt-`:style` statt String-`:style`.
- Kein nackter JS-Kommentar als Alpine-Ausdruck. Bei Teardown transient `null` → Templates mit `?.`/Null-Guards absichern (z. B. `draft?.fields?.urls`).
- Closure-Argumente von `Alpine.data(...)` sind in Blade-Ausdrücken NICHT sichtbar → `{{ __('...') }}` statisch oder `@js(__('...'))`. `@js()` NUR in Blade, nie in app.js.
- `<x-icon ::name>` reaktiv geht NICHT (server-gerendert) → per-Wert-Icons via x-show-Toggle (`passwords/_icon.blade.php`).
- Alpine-Komponenten (Stand v1.500): `vaultGallery`, `vaultFiles`, `notes`, `todos`, `bookmarks`, `contacts`, `invoices`, `passwords`, `backupRuns`, `devicePairing`, `paperlessSettings`, `notificationBell`, `cropModal`, `toastHub`, `publicShare`, `fileShare`.

---

## ZERO-KNOWLEDGE-ARCHITEKTUR (Kern)
- **Key-Hierarchie** (`vault.js`): Passphrase → Argon2id(ops=4/mem=256MiB) → KEK; KEK unwrapped per-user **Vault Key (VK)**. VK verlässt Browser nie. Recovery-Key als zweiter Wrap (32 Byte Zufall → generichash). Trusted-Device: VK mit non-extractable AES-256-GCM-Key in IndexedDB gewrapped (`VAULT_REMEMBER_DAYS`, frische IV je Wrap, Owner-Binding); Public-Computer: session-only + Idle-Lock (`VAULT_PUBLIC_IDLE_MINUTES`).
- **Opaque Store:** jedes Modul (Notes/Todos/Bookmarks/Files/Contacts/Invoices/Passwörter) liegt als Ciphertext in EINEM sealed Workspace-Manifest → `VaultStore` (`vault_store`, eine Zeile/User: sealed manifest + version + timestamp, optimistic lock via `SealedManifestStore`). Galerie hat EIGENES sharded sealed Manifest (`GalleryStore`).
- **Blob-Ledger + Modelle:** `GalleryBlob`, `FileBlob`, `ContactBlob` (+ `GalleryStore`, `VaultStore`). Bytes sind client-Ciphertext, Server streamt roh. Blobs zu Padmé-Buckets gepaddt, Manifest Padmé mit 4 KiB-Floor. **Padmé gilt auf ALLEN Write-Pfaden inkl. Extension** (`extension/src/crypto.js sealManifest` spiegelt `vault.js`).
- **`Support\BlobStore`** = generalisierter Blob-Zugriff. Orphan-Sweeps täglich: `gallery:sweep-orphans`, `files:sweep-orphans`, `contacts:sweep-orphans`.
- **Isolation-Traits** `AssignsOwner` (creating-Hook stempelt owner aus `Auth::id()`, nicht fillable = unfälschbar; `scopeOwnedBy`/`ownedBy()`) + `OwnsUserData` (globaler Read-Scope nur wenn `Auth::check()`; aus in Console/Queue). **Bulk-/destruktive/Export-Pfade explizit owner-scopen.**
- **Sharing (öffentliche Links, ZK):** `/s/{token}` für Galerie-Album, Datei UND Ordner — Share-Key im URL-Fragment (nie an Server), optionales Passwort = rate-limitiertes Gate, optionaler Download, Expiry. `PublicShare`-Modell (`kind`), Trait `Concerns\ManagesPublicShares`, `PublicShareController` (public), `ShareCrypto` in vault.js. Public-Share-ZK-Seiten brauchen das gebündelte JS (globale CSP `script-src 'self'` reicht).

## PASSWORTMANAGER + TRESORE (aktueller Stand)
- **Tresore (vormals Ordner):** share-ready Datenmodell. Jeder Tresor `{id, name, role}` mit `role ∈ {read, edit, manage}` (Owner=manage). Einmalige Client-Migration (`_migrateVaults`, durabler Flag `pwVaultMigrated` im Manifest) hat alle Einträge in einen Tresor **„Privat"** verschoben und Alt-Ordner entfernt. Umbenennen/Löschen manage-gated; letzter Tresor nicht löschbar. Datenmodell trägt bereits Rollen für **Phase 2: echtes ZK-Cross-User-Sharing** (per-Tresor-Schlüssel, Server-Mitgliedschaft, Einladungen, Key-Wrapping pro Nutzer) — NOCH NICHT gebaut.
- **Health:** schwach/wiederverwendet/breach (HIBP k-Anonymität), **kein-2FA** (Login ohne TOTP dessen Seite laut 2fa.directory app-2FA unterstützt), **CC-Ablauf** (Karte abgelaufen / ≤45 Tage). Health + Papierkorb zeigen tresorübergreifend alles.
- **2fa.directory-Hinweis** (`TwoFactorDirectoryController`, server-cached, SSRF-guarded): Domain→Doku-URL-Map, nur http(s)-URLs. Client matcht eigene Login-Domains (inkl. registrierbarer Parent-Domain) und zeigt 1Password-artigen Hinweis + Setup-Link. Extension holt denselben Datensatz direkt (host_permissions), 24h-Cache.
- **6 Typen** (login/password/card/wifi/license/server), per-item Versions-Historie, client-TOTP (WebCrypto HMAC-SHA1, RFC 6238), Passwort-Generator (Zeichen + merkbare Wörter en/de/es/fr/it, rejection-sampled CSPRNG), WiFi-QR, Favicon/BIMI (`PasswordIconController`, SSRF-guarded, data-URI im sealed Item), Multiselect+Bulk-Delete.

---

## BROWSER-EXTENSION (`extension/`, MV3, Chromium: Chrome/Brave/Vivaldi)
ZK Passwort-Autofill. Nutzt bestehende `/api/v1` (KEINE neuen Server-Read-Routes; Writes über bestehendes PUT `/store`). Pairing per Code wie Profil-Geräte (`/auth/pair`+`collect` → Sanctum-Bearer). Vault-Unlock via Passphrase (`/vault` KDF+wrapped VK → Argon2id → VK).
- **Krypto NUR im Background-SW** (`background.js`): VK nur in `chrome.storage.session`, nie Disk. `chrome.storage.local` hält nur Ciphertext/öffentliche Daten: `serverUrl,token,storeCipher,vaultMeta,tfaEntries,tfaAt` (alle unkritisch at rest). `crypto.js` spiegelt `vault.js` exakt (inkl. Padmé). Auto-Lock bei OS-Screen-Lock / 15 min idle. onMessage nur von eigener Extension (`sender.id`-Check), Input-Caps (passphrase/query).
- **Autofill** (`content.js`): Inline-Picker im Shadow-DOM, sichtbares In-Field-Icon, `focusin`+`composedPath()` fängt Shadow-DOM- und spät gerenderte Felder. Multi-Step-Login + Auto-Fill nach Pick. TOTP inkl. **segmentierter OTP-Boxen**. **Kreditkarten-Autofill** (cc-*-autocomplete + Heuristik), Ablauf-Format MM/YY vs MM/YYYY aus Placeholder/pattern/maxlength.
- **Anlegen:** manuelles Formular (Generator), **Passwort-Vorschlag** auf Registrier-Feldern, **Auto-Capture** bei Submit (In-Page-Prompt, escaped). **Löschen** (Papierkorb, Confirm). **QR-2FA:** `captureVisibleTab`+jsQR dekodiert otpauth-QR und hängt TOTP an. Popup = 1Password-Master-Detail.
- Build: `npm run build:ext` (eigenes `extension/vite.config.mjs`, bundlet libsodium+jsQR; `content.js` self-contained; `extension/dist` gitignored). **Manifest-Version = App-Version** (bei jedem Release mitziehen). CI `.github/workflows/extension-release.yml` baut bei `release: published`, pinnt Version=Tag, hängt Zip ans Release. **Kein Deploy** (nicht Teil der served App).

---

## SECURITY-ENTSCHEIDUNGEN, AUSNAHMEN & DESIGN-RATIONALE (Audit-Protokoll — VERBINDLICH fortzuschreiben)
Register aller bewussten Sicherheits-Trade-offs. **Jede neue Aufweichung hier eintragen (Datum + Begründung + Kompensation), im selben Commit.** Stand: Full-Audit 2026-07-18 (0 CVEs, keine ausnutzbare Lücke; Risk-Posture LOW).

**Bewusste Ausnahmen / akzeptierte Trade-offs:**
- **CSP `script-src 'unsafe-eval'`** (`SecurityHeaders.php`): behalten für Alpine.js (Function-Konstruktor). `unsafe-inline` ist aus script-src ENTFERNT; einziges Inline-Script = Theme-Bootstrap per sha256-Hash. Alpine nie über untrusted Daten.
- **CSP `style-src 'unsafe-inline'`**: nötig für Tailwind-Inline-Utilities; keine externen Stylesheets; Content sealed/opaque.
- **`img-src https://*.tile.openstreetmap.org`**: nur Leaflet-Tiles, bewusst eng.
- **Read-only rootfs NICHT gesetzt** (App-Container): serversideup-Base rendert nginx.conf beim Boot (braucht /etc-Write). Kompensiert: non-root, `cap_drop:[ALL]`+selektiv, `no-new-privileges`.
- **Build `network: host`** (compose): Host-Daemon hat keinen Bridge-Netz-DNS (netbird-Overlay). Nur Build-Zeit.
- **Extension `host_permissions https://*/* + http://*/*`**: Autofill auf beliebigen Login-Seiten + selbst-gehostetem Server; http wegen interner Dienste.
- **Extension `all_frames: false`** (nach 2× HIGH-Review): Injection nur Top-Frame → keine Credential-/Karten-Exposition in cross-origin-iframes. Shadow-DOM/späte Modals via `focusin`+`composedPath`. Defense-in-Depth: Karten-Autofill zusätzlich nur Top-Frame/same-origin (`CARDS_ALLOWED`).
- **`hostsMatch` nur Parent→Child** (Extension): example.com füllt auf accounts.example.com, nie umgekehrt. Kein PSL-Dep (Label-Heuristik + ccSLD-Liste) — bewusster Trade-off.
- **2fa.directory-Doku-URLs nur http(s)** (Server+Extension-Parse + Client-Guard): kein `javascript:`-XSS im href (MEDIUM behoben 2026-07-18).
- **SHA-1** nur HIBP-k-Anonymität (nur 5-Hex-Präfix raus) — protokollbedingt, KEIN Security-Hash.
- **`User.groups` NICHT fillable** (2026-07-18): treibt Admin-Gate → nur server-seitig via `forceFill` aus OIDC-Claim, nie mass-assign.
- **`PaperlessTerm.user_id` bleibt fillable**: `user_id` aus Server-Kontext (Sync-`userId`, kein Request-Input), `updateOrCreate` braucht es. Kein realer mass-assign-Vektor.
- **Backup-Models ohne schema-level user_id-Scope**: single-tenant, admin-only via `manage-global-settings` (fail-closed). Bei echtem Multi-User nachziehen.

**Bewusste, user-initiierte Boundary-Crossings (transient, opt-in, SSRF-guarded über `OutboundUrl`):** ML-Sidecar (Foto-Bytes, opt-in, Temp-unlink), Geocoding (grid-gesnappt, self-hosted möglich), Favicon/BIMI, HIBP, 2fa.directory, Paperless, ntfy/Webhooks/SMTP.

**Positiv bestätigt (Audit, keine Findings):** SQLi/Command-Injection (Process array-basiert), SSRF (`OutboundUrl`: link-local/metadata-Block, IP-Pinning gegen DNS-Rebinding, keine Redirects), XSS (DOMPurify client-Markdown, esc() Extension), Krypto (frische Nonces, keine Legacy-Algos), Session (encrypt+HttpOnly+Secure+SameSite+JSON-Serialisierung), Sanctum (180d abs/90d idle/remote-wipe/cap 3/per-device abilities), PKCE+state OIDC, DB-Dumps force-verschlüsselt (Argon2id SENSITIVE), Fehler-Traces redigiert, `/metrics` nur Aggregate token-gated (`hash_equals`), GDPR-Erase = Crypto-Shred inkl. Disk-Blobs.

**Header/CSP-Fixwerte:** X-Content-Type-Options nosniff, X-Frame-Options DENY + `frame-ancestors 'none'`, Referrer-Policy strict-origin-when-cross-origin, Permissions-Policy (ungenutzte Features aus), HSTS `max-age=63072000; includeSubDomains; preload` (nur bei TLS), **COOP `same-origin`**, `security.txt` unter `public/.well-known/`. Blob/Untrusted: `default-src 'none'; sandbox`. TLS 1.3 + HSTS via Caddy auf HOST.

**Infra-Härtung:** Docker Alpine-Base, Images per Digest gepinnt (App PHP 8.4, Node 22, db/valkey/photon; ML-Tag `${ML_IMAGE_TAG}` optionaler Profile-Service, Digest-Pin TODO). Non-root, `no-new-privileges`, `cap_drop:[ALL]` (App/db/valkey/**photon**) bzw. Drop `NET_RAW` (ml). Resource-Limits auf app/worker/**scheduler/ml/photon**. App-Port `127.0.0.1`. ImageMagick `policy.xml`. `AWS_EC2_METADATA_DISABLED=true`. `TRUSTED_PROXIES` private Ranges, nie `*`. Kein OCR/PDF-Toolchain im Container (extern Paperless).

**CI/Supply-Chain:** `.github/workflows/security-scan.yml` (composer audit, npm audit, Trivy fs→SARIF, SPDX-SBOM); `.github/dependabot.yml` (composer, npm root+extension, github-actions, docker, wöchentlich). **Offen:** Commit-Signing + gitleaks pre-commit/CI (History sauber, keine Rotation nötig); ML-Image Digest-Pin.

**Beobachtungspunkte:** `PhotoEditTest` segfaultet (imagick/GD) in vollen Läufen und maskiert Folgetests → Tests in Häppchen `--filter`; „0 failures" aus vollem Lauf UNZUVERLÄSSIG.

## GETEILTE ABSTRAKTIONEN (nutzen, nicht duplizieren)
`OutboundUrl` (SSRF), `Support\BlobStore`, `AssignsOwner`/`OwnsUserData`, `vault.js` (client crypto core), `decrypt.worker.js` (Worker-Pool, Galerie↔Files), `Support\ArchiveName` (Zip-slip), `Support\KeepBlankSecrets::preserve()`, `ChannelNotifier`, `ImageManagerFactory`, `DiskTempFile`, `SealedManifestStore` (optimistic-lock store), `ManagesPublicShares`. app.js: `apiJson()` hinter `_json`, `zkModule()`-Mixin.

---

## DEPLOY-RITUAL (server.p37.nexus) — pro Deploy explizite User-Freigabe (Klassifikator blockt sonst)
Remote-Shell = **fish** → in `bash -lc '…'` wrappen.
```
ssh -p 2222 -i ~/.ssh/id_priv -o StrictHostKeyChecking=no root@server.p37.nexus \
  bash -lc "'cd /srv/ledgerline && git fetch -q --tags && git checkout -q vX.Y.Z \
  && IMAGE_TAG=vX.Y.Z docker compose build app && IMAGE_TAG=vX.Y.Z docker compose up -d'"
```
- **IMMER `up -d` ohne Service-Namen** — app/worker/scheduler teilen `ledgerline:${IMAGE_TAG}`. Danach `docker compose ps` prüfen: alle drei auf neuem Tag.
- Alte Images löschen (nur aktuelles + vorheriges Tag): `docker images --format "{{.Repository}}:{{.Tag}}" | grep "^ledgerline:" | grep -v -E "vNEU|vVORHER" | xargs -r docker rmi`.
- **NIE `docker compose pull`** (lokal gebautes Image). Migrationen laufen automatisch beim App-Start.
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://home.kiefer-networks.de/up` → **200**.
- Infra: Debian 13, Docker; `/srv/ledgerline`, App-Port **8300** (bind 127.0.0.1, `APP_PORT`), Domain **home.kiefer-networks.de** (DNS IPv6-only). Caddy auf HOST (`systemctl restart caddy`, admin-API aus). Build-DNS-Quirk: compose `build.network: host`. Worker `--scale worker=N`. ML im `ml`-Profil, Photon im `geocode`-Profil. Server single-user, führt KEINE Tests.

## RELEASE-RITUAL (Git Flow)
1. Auf `develop`. Version-Bump `config/app.php` (`env('APP_VERSION','X.Y.Z')`) + bei Extension-Änderung `extension/manifest.json` gleiche Version.
2. `vendor/bin/pint --dirty` → passed. `npm run build` (+ `npm run build:ext` bei Extension). **`npm run lint`** (eslint no-undef) grün. **`npm run test:js`** (Vitest) grün. EN/DE-Parität. AI-Scan (grep geänderte Dateien). ZK-Scan (keine neuen Klartext-Spalten/Server-Render-Pfade). **CLAUDE.md aktualisiert** (Features + Security-Register).
3. Tests: `php artisan test --teamcity` (Hook erzwingt `--teamcity`; PhotoEdit-Segfault → in Häppchen `--filter`). **JS-Unit-Tests: `npm run test:js`** (Vitest).
4. `php artisan view:cache`. Commit. `git checkout main && git merge --no-ff develop && git tag vX.Y.Z && git push origin main develop --tags`. `gh release create`. Zurück `develop`. Deploy (s. o., außer reine Extension-/CI-Änderung).
- Hotfixes = Patch-Bump. Docker-Dateien nur auf `main`/`develop` — Tag auschecken zum Deployen.

## REFACTORING app.js — GRÖSSTENTEILS ABGESCHLOSSEN (v1.500.3–v1.500.20)
app.js **8085 → ~753 Z.** (Bootstrap + Store-/Komponenten-Registrierungen). Verhaltensneutral in einzeln build+test+**lint**-verifizierten Scheiben; jede Scheibe deployed. Struktur:
- `resources/js/shared/` (12): `api` (csrfToken/jsonHeaders/apiRequest), `dom` (escapeHtml/saveBlobAs), `vector-math` (normVec/dotVec), `wordlists` (PW_WORDS), `contact-utils`, `ocr` (tesseract), `lazy-loaders` (leaflet/codemirror — `cmModule` als Live-Binding), `file-categories`, `blob-io` (fetch/decrypt-Worker-Pool/thumbLane/delete-queue; Worker `../decrypt.worker.js`), `markdown`, `zk-module` (bootStore/bootGalleryStore/zkModule), `padme` (padmeSize/padBlob).
- `resources/js/components/` (8): toast-hub, crop-modal, backup-runs, device-pairing, paperless-settings, notification-bell, **contacts** (inkl. inline vCard-Codec), **passwords**, **files** (inkl. fileText/fileEmb-Suchcaches), **gallery** (Worker `../scan.worker.js`). Muster: `export default factory` → in app.js `Alpine.data(name, factory)`.
- **eslint `no-undef`-Netz** (`eslint.config.mjs`, `npm run lint`) fing 3 echte Bugs, die der Bundler NICHT meldet: `cmModule` (Files-Editor, Live-Bug seit v1.500.9), `scan.worker.js`-Pfad, toter `_contactsReconAt`. **Regel: nach jeder Extraktion `npm run lint` grün.**
- **Konvention „Alpine in EINER Datei" aufgehoben** (Freigabe 2026-07-18).
- **Alle 14 Komponenten raus** (v1.500.24: publicShare, fileShare, invoices, todos, notes, bookmarks; FOLDER_ICONS in bookmarks.js). **app.js ~753 Z.** — nur noch Bootstrap, Stores (confirm/nav/vault/paperless) + LLStore/LLGalleryStore-Singletons + wenige Helfer inline.
- Große Komponenten sind **nur im Browser testbar** (Krypto/Worker/Autofill) — headless nur build+test+lint; nach Deploy visuell prüfen.

## HISTORIE (Kurz)
- **v1.298 → ~1.480** (2026-07): Umbau plaintext → zero-knowledge (Vault-Kern, opaque store, ZK-Galerie, ML client-seitig, ZK-Kontakte/Invoices, Photon-Geocoding). **Entfernt:** Mail, Kalender/CalDAV, CardDAV.
- **~1.485–1.499**: ZK-File/Folder-Sharing; Passwortmanager-Suite (Health/HIBP, Generator, Import, Multi-URL/Custom-Fields, Favicon); **Browser-Extension** (Autofill, TOTP, Karten, Anlegen/Capture, QR-2FA, 1Password-Popup); CI baut Extension je Release; 2fa.directory-Hinweis + „kein-2FA"/CC-Ablauf-Health; **Tresore** (share-ready) + Migration nach „Privat".
- **v1.499.1–v1.500.1**: Full-Security-Audit umgesetzt — Dep-Upgrades (pdfjs 6, laravel 13.20, dompurify/vite/tailwind), Härtungen (User.groups, Extension-Input/Sender, COOP, security.txt, `.env.*`-ignore), Docker-Limits/photon-cap-drop, CI Trivy+Dependabot. CLAUDE.md als Security-Register etabliert.

## MEMORY & CHECKS
- Memory: `~/.claude/projects/-Users-malte-kiefer-Entwicklung-ledgerline/memory/` (Index `MEMORY.md`).
- Icon-Audit: `x-icon name="…"` gegen Keys in `icon.blade.php` — MISSING = unsichtbar.
- ZK-Check vor Merge: kein neuer Server-Pfad, der Klartext-Inhalt sieht/ableitet.
- Vor jedem Commit: CLAUDE.md aktualisiert? Neue Security-Entscheidung/-Aufweichung ins Register (Datum+Begründung+Kompensation)?
