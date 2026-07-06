# Ledgerline — Projekt-, Anforderungs- & Session-Kontext

Self-hosted Laravel-App, persönliche Suite: **Mail, Kalender, Kontakte(CardDAV),
Galerie, Dateien, Notizen, Todos, Lesezeichen, Paperless**. Single-tenant Server,
aber code-seitig **voll Multi-User-isoliert + Cross-User-Sharing**.

Stand nach Session 2026-07-05/06. Version **v1.298.4** (live https://home.kiefer-networks.de, `/up`=200).
Diese Datei = vollständiger Kontext zum Weiterarbeiten nach Neustart. Ergänzende
Detail-Historie in `~/.claude/projects/-Users-malte-kiefer-Entwicklung/memory/`.

---

## Stack
- Laravel 13 / PHP 8.4, PostgreSQL (prod) / sqlite (tests), Valkey (predis, kein phpredis).
- Frontend: **Alpine.js in EINER Datei** `resources/js/app.js` + Blade + Tailwind 4 + Vite. Kein externes CDN — alles via npm/Vite gebündelt.
- Auth: Pocket-ID OIDC (Socialite `pocketid`), `groups`-Claim → `users.groups`.
- Mail: webklex/php-imap (lesen/archiv), Symfony Mailer/EsmtpTransport (SMTP senden). DAV: sabre/dav (CalDAV+CardDAV). Bilder: intervention/image v4 + imagick. Backup-Crypto: ext-sodium (ArchiveCipher).

---

## STEHENDE ANWEISUNGEN DES USERS (dauerhaft gültig)
- **Gründlichkeit/Parallelität:** große Aufgaben (Audits, Redesign, Feature-Batches) mit **min. 45 parallelen Agenten/Workflows**. Vollständige Analyse aller betroffenen Module, nicht oberflächlich.
- **„Sei hart, keine Kompromisse"** bei Audits/Fixes: ALLE Findings adressieren, strikt RFC/POSIX/PHP-/Laravel-Best-Practice. Immer zusätzlich Scan auf toten/ungenutzten/doppelten/nicht-generalisierten Code → so viel wie möglich generalisieren.
- **„Mache alle weiteren Schritte ohne Nachfragen" / „setze alles um":** eigenständig bis Ende inkl. Release+Deploy. Anhalten NUR bei: (a) Deploy-Freigabe (Klassifikator blockt Prod-Deploy immer), (b) Artefakten die nur der User hat (Screenshots, Fehler-Traces), (c) echten irreversiblen Entscheidungen.
- **Kommunikation:** knapp, technisch, deutsch, handlungsorientiert, keine Floskeln.
- Jede Änderung = **eigenes getestetes Git-Flow-Release + Deploy** (nicht sammeln, außer User sagt es).

## Feste Konventionen (nicht verletzen)
- **Monochrome Icons** nur via `<x-icon name="...">` (heroicons-outline, `currentColor`). Kein emoji, keine Farbe. Toggle-States = outline vs `-solid`, nicht Farbe. Unbekannter Name → **leeres SVG (unsichtbar)**, nie Fehler → neue Icons als Pfad in `resources/views/components/icon.blade.php`.
- Geteilte UI-Komponenten: `x-button` (variant primary=gray-900/secondary/danger, `min-h-11` Touch-Floor), `x-page-heading` (:title/:subtitle + `<x-slot:actions>`), `x-icon`, `x-sheet` (Off-canvas), `x-nav`/`x-mobile-nav`, `x-notification-panel`. Keine Einzel-Neubauten.
- **EN/DE-Parität**: jede Lang-Änderung in `lang/en/*` UND `lang/de/*`, identische Keys. Der `lang-key-existence-preflight`-Hook meldet verschachtelte Keys (`ui.x`) fälschlich als fehlend — mit PHP verifizieren.
- **Keine AI-Referenzen** in Code/Kommentaren/Commits/PRs/Releases (kein Claude/Anthropic/Copilot/„generated with AI", kein `Co-Authored-By`). Commits: englisch, imperativer Betreff ≤72 Zeichen + Body. `git commit --no-verify`. Repo-Git-Email: `malte.kiefer@kiefer-networks.de`.
- Nur `README.md`/`CLAUDE.md` als Markdown im Repo.
- Module = **plaintext + Alpine-Client über JSON-APIs** (kein Vault mehr, nichts verschlüsselt außer Passwörter/Secrets `encrypted` cast). Controller: `index()`=GET `/{mod}/data`, PATCH=Toggle, PUT=Full-Save. Web-Route-Validation → redirect+session errors; JSON nur `api/*`/XHR.

## Layout-/UI-Entscheidungen (getroffen, gelten weiter)
- **Mobile ≠ Desktop**, bewusst getrennt.
- Mobile Navigation = **Hamburger-Menü, Drawer fährt von LINKS ein** (`x-mobile-nav`, `$store.nav.toggleNav()`). Die früher gebaute **Bottom-Tab-Bar wurde VERWORFEN** (instabil, Klicks gingen nicht) — NICHT wieder einführen. Kein Bottom-Padding.
- Modul-Sidebars: Desktop = weiße **Card/Rail** (`hidden md:block`), mobil = Slide-over `x-sheet side=left store=sidebarOpen`. Body je Modul in `<module>/_sidebar_content.blade.php` (einmal editieren = beide Varianten). Sidebar-Card **nur so hoch wie Inhalt**, nicht auf Content-Höhe strecken.
- `Alpine.store('nav')` {navOpen, sidebarOpen, toggleNav, toggleSidebar, closeAll}. Single-source Nav = `config/navigation.php` (primary[]+more[]). `<body>` trägt `x-data` (sonst binden Hamburger/Drawer nicht — war ein Bug).
- Hover-Row-Actions touch-fest: `flex md:hidden md:group-hover:flex` statt `hidden group-hover:flex`.
- Modul-Konsistenz: gleiche Muster überall (z. B. Sidebar-Fußaktionen als gestapelte Text-Links wie im Kalender, nicht mal Buttons mal Links).

## Alpine-Gotchas (haben in dieser Session 2 Live-Crashes verursacht)
- `labels` ist das **Closure-Argument** von `Alpine.data('vaultMail', (labels={}) => …)` — in JS-Methoden via Closure ok, aber **NICHT in Blade-Template-Ausdrücken** (`x-text`/`:bind`) sichtbar → „labels is not defined". Für Template-Strings: `{{ __('...') }}` statisch oder `@js(__('...'))`. NIE `labels.x` in x-bind/x-text.
- Kein nackter JS-Kommentar als Alpine-Ausdruck (`@paste="/* … */"` → SyntaxError, killt Komponente).
- `<template x-if>` braucht genau 1 Root-Element. Objekt-`:style` statt String-`:style` (String überschreibt `x-show`'s display:none).

---

## PER-USER-ISOLATION + SHARING (Kern-Sicherheit)
Umbau single-workspace → per-user (v1.241–1.251), COMPLETE + 2 harte Audits.
- **`AssignsOwner`** (Basis-Trait): `ownerColumn()` (Photo→`uploaded_by`, sonst `user_id`), `creating`-Hook stempelt owner aus `Auth::id()` (nicht fillable = unfälschbar), `scopeOwnedBy()` → `Model::ownedBy($uid)`.
- **`OwnsUserData`**: globaler Read-Scope `where owner = Auth::id()` NUR wenn `Auth::check()` (Web). Aus in Console/Queue/DAV (die scopen über den Record-Owner).
- **`SharesWithUsers`**: owned ODER geteilt; `isOwnedBy()` + Write-Guard. **`isOwnedBy()` existiert NUR hier**, NICHT auf `OwnsUserData` (z. B. MailAccount) → dort explizit `(int)$m->user_id === (int)auth()->id()`. (War v1.297 der echte 500-Grund beim Mail-Senden.)
- **KRITISCH:** Der Write-Guard von `SharesWithUsers` ist Eloquent-Event-basiert → **Query-Builder Bulk-delete/update umgehen ihn**. Alle Bulk-/destruktiven/Export-Pfade explizit owner-scopen (`withoutGlobalScopes()->where(owner)` bzw. `ownedBy()`).
- **Sharing:** `resource_shares` (morph, owner_id, shared_with_user_id, permission read|write) auf Note/StoredFile/FileFolder/Calendar/AddressBook/Photo. `ResourceShareController` (owner-only). Share-Modal `partials/share-modal.blade.php` + `shareMixin(cfg)`. Intern → `AppNotification` (category 'share') + Copy-Link + E-Mail (wenn `ChannelNotifier::mailConfigured()`). Extern → `PublicShare` (Token) read-only unter `/p/{token}` (HTML + `.ics`/`.vcf`, script-less CSP). Read-only/virtuelle Kalender NICHT teilbar.
- **DAV-Sharing:** CalDavBackend/AddressBookBackend zeigen geteilte Collections (`shared-<id>`), Write braucht write-share, rename/delete owner-only.
- **Admin-Gate:** Gate `manage-global-settings` (`User::managesGlobalSettings()`, OIDC-Gruppe `POCKETID_ADMIN_GROUP`). **Fail-closed bei Multi-User** wenn Gruppe leer, Single-User erlaubt (v1.282 H3). Infra-Settings admin-only; persönliche Settings + CardDAV-Creds offen.
- **Settings-Split:** `AppSettings` (global/infra: SMTP-Notify, Paperless, ntfy/webhook, gallery-processing, export-limits) vs **`UserSetting`** (per-user: calendar-prefs, holidays/birthdays, reminder_channels, gallery_columns, file_max_versions, **mail_sync_minutes**). `UserSetting::for($uid)`.

## SICHERHEITS-POSTURE (aus 4 harten Audits, alle gefixt+deployed)
- **SSRF-Guard `App\Support\OutboundUrl`**: blockt link-local/metadata (inkl. IPv4-mapped-IPv6 `::ffff:169.254.169.254`), `client()` = IP-gepinnte PendingRequest (DNS-Rebinding-TOCTOU dicht), `hostAllowed()`. Genutzt von CalendarFeedFetcher, PaperlessClient, ChannelNotifier (ntfy/webhook), Mail-Host-Guard, Avatar-Fetch. AWS SDK IMDS aus: `AWS_EC2_METADATA_DISABLED=true` (S3 = Hetzner nbg1.your-objectstorage.com, Bucket knledger).
- **Rate-Limits/Throttles** flächendeckend (Share/unlock/search/auth/mail-reader/calendar-import/feed/DAV `throttle:dav` 60/min/IP/etc.), Streaming-Caps (feed/archive/ics-vcf), Array-Caps auf Manifests.
- **Datei-Integrität:** `file_blobs` (Uploader-Tracking) → sync() lehnt fremde Blob-Referenzen ab; Quota reconcile gegen Disk-Size (`config/files.quota_mb`); referenz-sicheres Blob-Delete + Orphan-Sweep (`PruneTrashedFiles`). `FileVersion` (Snapshot bei Blob-Change), per-user `file_max_versions` 1–10.
- **XSS/Injection:** bookmark/todo-url regex `^https?://` (kein javascript:/data:); LIKE-Metachars escapen (`%`/`_`/`\` ESCAPE `\`, reservierte Cols `to`/`cc` grammar-wrapped); RRULE/CRLF-Sanitise bei ICS-Import+CalDAV; owner-scoped `exists`-Rules; Markdown server-side escaped+sanitised; Client DOMPurify für Compose-Quote.
- **Mail:** SMTP `setRequireTls(true)` (außer ssl/none); IMAP/SMTP Host-Egress-Guard (kein metadata/link-local); BODY.PEEK (setzt kein `\Seen`); MIME-Header-Dekodierung `MimeHeader::decode()`.
- **Auth:** OIDC dup-email 500-Guard; `install_claims` first-claim-Tombstone (M10, gelöschtes Konto nicht re-provisionierbar); RP-initiated OIDC-Logout; last_login.
- **GDPR/Erasure:** Self-delete Cascade via `config/user_data.php` (`UserDataContributor` je Modul) + `PurgeUserAccount`; Data-Export-Zip; Session-List/Revoke; `PruneShares` täglich; Purge-Vollständigkeit (file_versions/file_blobs/todo_lists/recipient resource_shares/note_shares).
- **Headers/CSP:** App setzt eigene CSP/HSTS (kein `script-src 'unsafe-inline'`, `'unsafe-eval'` für Alpine behalten); script-less CSP für Share-/Public-Seiten; sandboxed iframe für E-Mail-HTML (Konsolen-„Blocked script execution in about:srcdoc" = GEWOLLT, harmlos); file-version/avatar nosniff+sandbox.
- **Infra-Härtung:** ImageMagick `policy.xml` (blockt MSL/MVG/URL/PS/PDF + Ressourcen-Caps, in prod aktiv); ffmpeg-Pfad config-only; SoftDeletes (Note/Todo/Bookmark/StoredFile). ext-sodium ArchiveCipher für Backups.
- **Bewusst akzeptiert (dokumentiert, NICHT „fixen"):** Mass-Assignment auf Models (Controller bauen explizite Arrays, kein request-driven Pfad); Tasks-Kalender zeigt alle Todos des Owners; VTIMEZONE nicht emittiert (TZID+IANA reicht); unresolvable-host im Default-SSRF-Posture erlaubt (Docker-interne Namen; hardened-mode blockt).

## GETEILTE ABSTRAKTIONEN (Generalisierungen — nutzen, nicht duplizieren)
`OutboundUrl` (SSRF), `AssignsOwner::scopeOwnedBy`/`ownedBy()`, `Support\BlobStore::disk()` (statt Storage::disk-Literale), `Support\ArchiveName` (unique/sanitize, Zip-slip-sicher), `Support\KeepBlankSecrets::preserve()` (leeres PW behält gespeichertes), `Support\Shareable` (slug↔class-Registry; PublicShare behält EIGENE 3-Typen-Allowlist, Registry NICHT aufweiten), `Concerns\RespondsFlexibly::flexible()`, `Concerns\PurgesOwnedTrash::emptyOwnedTrash()` (per-Model forceDelete, damit Observer feuern), `Concerns\RedirectsToSettings::savedRedirect()`, `Services\Mail\SmtpSender`/`MimeHeader`, `ChannelNotifier` (ntfy/webhook/mail), `Contacts\DavChangeLog`/`ContactPersister`, `ImageManagerFactory`, `DiskTempFile`, `Gallery\PhotoTransform`, `AbstractSearchProvider::matchAny()`, `Support\Tags`. app.js: module-level `apiJson()` hinter `_json`.

---

## MODUL-INVENTAR (Features)
- **Mail:** plaintext-Konten (PW encrypted), live IMAP-Reader + stündliches lokales Archiv (.eml auf files-Disk `mail/{blob}`, `mail:sync` per-folder-resilient, per_run_cap 100/folder + 300s Budget), Archiv-Suche (subject/from/to/cc/body/attachment/date, `mail:reindex`), read-from-archive (instant), inline-cid-Bilder, SMTP-Senden + **mehrere Identitäten/Signaturen pro Konto**, Compose (Rich-Editor, To/Cc/Bcc-Autocomplete, Anhang aus Upload/Galerie/Dateien), .eml-Download (einzel/Auswahl/Archiv), Konto-Löschen mit Archiv-Wahl. Details: memory `ledgerline-mail.md`.
- **Kalender/CalDAV:** Events/Recurrence/Reminders, Todos-als-VTODO (2-Wege), ICS-Import+Subscriptions, Settings (week/tz/birthdays/holidays), voll RFC-gehärtet (memory `ledgerline-calendar*`).
- **Kontakte/CardDAV:** vCard 4.0, mehrere Adressbücher, Gruppen↔CATEGORIES, Avatar (PHOTO base64), .vcf Import/Export, Duplikat-Review, Galerie-Person→Kontakt-Link.
- **Galerie:** Photo-Model, PhotoStorage, Leaflet-Karte, HEIC/HEIF/AVIF + Apple Live/Motion Photos, Duplikat-Erkennung (pHash+CLIP/pgvector), Gesichtserkennung „People" (immich-ml buffalo_l + pgvector-Clustering).
- **Dateien:** client-driven Manifest-Sync (StoredFile/FileFolder/FileVersion/FileBlob), Versionierung + Restore-GUI, per-user Quota, Backup (full/incremental).
- **Notizen/Todos/Lesezeichen:** plaintext, server-rendered Markdown (Notizen), Reminder (Todos), Ordner/Tags/Favoriten.
- **Paperless-ngx:** server-side Token, stündl. Term-Sync, Transfer-Modal.
- **Downloads-Center:** async worker-gebaute Export-Zips (Galerie+Dateien), 7-Tage-Retention, Size-Split, ntfy/mail/webhook/bell-Notify.
- Globale Suche + Dashboard über alle Module (auto-scoped).

---

## DEPLOY-RITUAL (server.p37.nexus) — pro Deploy explizite User-Freigabe (Klassifikator blockt sonst)
Remote-Shell = **fish** → in `bash -lc '…'` wrappen.
```
ssh -p 2222 -i ~/.ssh/id_priv -o StrictHostKeyChecking=no root@server.p37.nexus \
  bash -lc "'cd /srv/ledgerline && git fetch -q --tags && git checkout -q vX.Y.Z \
  && IMAGE_TAG=vX.Y.Z docker compose build app && IMAGE_TAG=vX.Y.Z docker compose up -d'"
```
- **IMMER `up -d` ohne Service-Namen** — app/worker/scheduler teilen `ledgerline:${IMAGE_TAG}`; `up -d app` ließ worker+scheduler auf altem Image zurück (bis v1.299.1 liefen 3 Versionen parallel). Danach mit `docker compose ps` prüfen: alle drei auf dem neuen Tag.
- Nach dem Deploy alte Images löschen (nur aktuelles + vorheriges Tag behalten): `docker images --format "{{.Repository}}:{{.Tag}}" | grep "^ledgerline:" | grep -v -E "vNEU|vVORHER" | xargs -r docker rmi`.
- **NIE `docker compose pull`** (lokal gebautes Image). Migrationen laufen automatisch beim App-Start.
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://home.kiefer-networks.de/up` → **200**.
- Infra: Debian 13, Docker; `/srv/ledgerline` Port **8300** (bind 127.0.0.1), Domain **home.kiefer-networks.de** (DNS IPv6-only). Caddy läuft auf dem HOST (`systemctl restart caddy`, NICHT reload — admin-API aus). Build-DNS-Quirk: compose `build.network: host` (netbird-Resolver). App-Cap `APP_MEMORY_LIMIT=8g`, Worker skalieren `--scale worker=10`. ML-Service (immich) im `ml`-Profil. Details: memory `ledgerline-docker-selfhost.md`. Server ist single-user, führt KEINE Tests.

## RELEASE-RITUAL (Git Flow)
1. Auf `develop`. Version-Bump `config/app.php` (`env('APP_VERSION','X.Y.Z')`).
2. `vendor/bin/pint --dirty` → passed. `npm run build`. EN/DE-Parität. AI-Scan (grep geänderte Dateien).
3. Tests: `php artisan test --teamcity` (Hook erzwingt `--teamcity`). **BUG: `PhotoEditTest` segfaultet** (imagick/GD, „Premature end of PHP process") in vollen Läufen und **maskiert alle danach alphabetisch laufenden Tests** → in Häppchen mit `--filter='…'` laufen (z. B. `Mail|Contact|Settings`, separat `Public|Resource|User|Vault`). „0 failures" aus vollem Lauf ist UNZUVERLÄSSIG. Deploy unberührt.
4. `php artisan view:cache`. Commit. `git checkout main && git merge --no-ff develop && git tag vX.Y.Z && git push origin main develop --tags`. `gh release create`. Zurück `develop`. Deploy (s. o.).
- Hotfixes = Patch-Bump (`1.298.1`).
- Docker-Dateien nur auf `main`/`develop` — Tags sind neuer, also Tag auschecken zum Deployen (funktioniert, da Tag > Docker-Einführung).

---

## DIESE SESSION (v1.293 – v1.298.4)
- **v1.293.0** — letzte 2 Audit-Generalisierungen R-SETTINGS + R-JS → Audit-Programm 100% zu (alle 68 Findings + 11 Generalisierungen v1.282–1.293).
- **v1.294.0** — #9 MIME-Betreff-Dekodierung (`MimeHeader`); #12 Kalender-Sidebar cursor-pointer; #13 Galerie Hover-Motion nur `pointer:fine`.
- **v1.295.0** — #14 `mail_sync_minutes` global→per-user (Migration+UserSetting); #8 SMTP-nicht-konfiguriert-Warnung (`smtpConfigured()`, Compose-Banner, Send disabled, echter Fehler sichtbar). Neues Icon `exclamation-triangle`.
- **v1.296.0** — #1 größeres Compose (max-w-4xl); #2 To/Cc/Bcc-Autocomplete (`GET /mail/recipients`); #3 Editor Font/Größe/Ausrichtung/Clear; #7 Galerie-Anhang Thumbnail-Grid + Suche. Fix 2 Alpine-Crashes (@paste, labels).
- **v1.297.0** — #4/#5 mehrere Identitäten/Signaturen pro Konto (`mail_identities`, `MailIdentityController`, Compose-Selector). Fix echter 500 (`MailAccount::isOwnedBy` existierte nicht → user_id-Check).
- **v1.298.0** — #10 Mail-Leiste (trash vs `x-circle` delete, monochrom, gruppiert); #11 Kontakt-Initialen + `user`-Icon; Cache-Flush `msg:v3`/`msgs:v2`.
- **v1.298.1** — Hotfix: Anhang-Picker `labels.*`-Template-Crash → `@js()`/statisch.
- **v1.298.2** — `reply-all`-Icon statt 2 überlappender Pfeile; Kontakte-Sidebar-Aktionen als Text-Links wie Kalender.
- **v1.298.3** — Mail-Settings-Karte `$admin`→`$personal` (Sync ist per-user).
- **v1.298.4** — alle Settings-Karten alphabetisch sortiert.

## NOCH OFFEN / ROADMAP
- **#6 „Anhang aus Dateien wirft JS-Fehler"** — User zurückgestellt (Trace nie geliefert). Wahrscheinlich durch v1.296 @paste-Fix behoben. Nach Neustart testen; falls Fehler → Trace holen. Code: `openAttachPicker('files')` app.js ~3364, Picker-Modal `mail/index.blade.php` ~596.
- **Roadmap (user-bestätigt, pausiert):** 1. Dark Mode mit Toggle (per-user UserSetting, Tailwind4 `@custom-variant dark`, `dark:` app-weit, kein SW/Manifest bisher). 2. Vollständige PWA (Manifest + Service-Worker + Icons + offline). 3. 16-Release Feature-Roadmap (P0 Security zuerst) — Liste in memory `ledgerline-mobile-redesign.md`.
- Nice-to-have: ~90 tote Lang-Keys aufräumen; Contact-Model hat kein Isolation-Trait (manuelles `whereIn address_book_id` — alle `Contact::`-Call-Sites prüfen); L30 DAV-Security-Headers (slim wrapper, DavController macht `$server->start();exit`).

## MEMORY & CHECKS
- Memory: `~/.claude/projects/-Users-malte-kiefer-Entwicklung/memory/` (Index `MEMORY.md`). Wichtig: `ledgerline-mail.md`, `ledgerline-user-isolation.md`, `ledgerline-audit-2026-07-05.md`, `ledgerline-docker-selfhost.md`, `ledgerline-mobile-redesign.md`, `ledgerline-workflow.md`, sowie modul-spezifische (calendar/contacts/gallery/faces/files/notes/todos/bookmarks/paperless/downloads/duplicates).
- Icon-Audit: `x-icon name="…"`-Namen gegen Keys in `icon.blade.php` — MISSING = unsichtbar.
- Template-`labels.*`-Scan: `grep -rnE '(:[a-z-]+|x-(text|show))="[^"]*labels\.' resources/views/` muss leer sein.
