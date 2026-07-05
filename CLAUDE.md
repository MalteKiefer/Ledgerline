# Ledgerline — Projekt- & Session-Kontext

Self-hosted Laravel-App (persönliche Suite: Mail, Kalender, Kontakte/CardDAV,
Galerie, Dateien, Notizen, Todos, Lesezeichen, Paperless). **Single-tenant
Server, aber code-seitig voll Multi-User-isoliert.**

Diese Datei = Stand nach der Session vom 2026-07-05/06. Aktuelle Version:
**v1.298.4** (deployed, live auf https://home.kiefer-networks.de , `/up`=200).

---

## Stack
- Laravel 13 / PHP 8.4, PostgreSQL (prod) / sqlite (tests), Valkey.
- Frontend: **Alpine.js** in EINER Datei `resources/js/app.js` + Blade + Tailwind 4 + Vite.
- Auth: Pocket-ID OIDC (Socialite `pocketid`).
- Mail: webklex/php-imap (lesen/archiv) + Symfony Mailer (SMTP senden), sabre/dav (CalDAV/CardDAV).

## Feste Konventionen (NICHT verletzen)
- **Monochrome Icons** nur über `<x-icon name="...">` (heroicons-outline, currentColor). Kein emoji, keine Farbe. Unbekannter Name → **leeres SVG (unsichtbar)**, nie Fehler. Neue Icons: Pfad in `resources/views/components/icon.blade.php` ergänzen.
- **EN/DE-Parität**: jede Lang-Änderung in `lang/en/*` UND `lang/de/*`, identische Keys.
- **Keine AI-Referenzen** in Code/Commits/Releases (kein "Claude", "Anthropic", "generated with AI", kein Co-Authored-By).
- Module sind **plaintext + Alpine-Client über JSON-APIs** (kein Vault mehr, nichts verschlüsselt außer Passwörter/Secrets `encrypted` cast). Controller: `index()`=GET `/{mod}/data`, PATCH=Toggle, PUT=Full-Save. Web-Route-Validation → redirect+session errors; JSON nur `api/*`/XHR.

## Per-User-Isolation (WICHTIG, security-relevant)
- Basis-Trait `AssignsOwner` (ownerColumn(), creating-hook stempelt owner, `scopeOwnedBy()` → `Model::ownedBy($uid)`).
- `OwnsUserData` = auth-gated global read scope. `SharesWithUsers` = zusätzlich Cross-User-Sharing.
- **`isOwnedBy()` existiert NUR auf `SharesWithUsers`** (Note/File/Album/Calendar), **NICHT auf `OwnsUserData`** (z. B. MailAccount). Auf OwnsUserData-only-Modellen: explizit `(int)$m->user_id === (int)auth()->id()` prüfen. (Dieser Fehler war v1.297 der echte 500-Grund beim Mail-Senden.)
- Bulk-/Query-Deletes müssen explizit owner-scoped sein (globaler Scope greift bei Query-Builder-Bulk nicht).

## Alpine-Gotchas (in dieser Session zwei Live-Crashes verursacht)
- `labels` ist das **Closure-Argument** von `Alpine.data('vaultMail', (labels={}) => …)` — in JS-Methoden via Closure nutzbar, aber **NICHT** in Blade-Template-Ausdrücken (`x-text`/`:bind`) sichtbar → dort „labels is not defined". Für konstante Strings im Template: `{{ __('...') }}` statisch oder `@js(__('...'))`. NIE `labels.x` in x-bind/x-text.
- Kein nackter JS-Kommentar als Alpine-Ausdruck (`@paste="/* … */"` → SyntaxError, killt Komponente).
- `<template x-if>` braucht genau 1 Root-Element.

---

## Deploy-Ritual (server.p37.nexus) — pro Deploy explizite User-Freigabe nötig (Klassifikator blockt sonst)
Remote-Shell ist **fish** → Befehle in `bash -lc '…'` wrappen.
```
ssh -p 2222 -i ~/.ssh/id_priv -o StrictHostKeyChecking=no root@server.p37.nexus \
  bash -lc "'cd /srv/ledgerline && git fetch -q --tags && git checkout -q vX.Y.Z \
  && IMAGE_TAG=vX.Y.Z docker compose build app && IMAGE_TAG=vX.Y.Z docker compose up -d app'"
```
- **NIE `docker compose pull`** (lokal gebautes Image). Migrationen laufen automatisch beim App-Container-Start (`migrate` steht sonst auf „Nothing to migrate").
- Verify: `curl -s -o /dev/null -w "%{http_code}" https://home.kiefer-networks.de/up` → **200**.
- Server ist single-user; führt KEINE Tests aus.

## Release-Ritual (Git Flow)
1. Code auf `develop`. Version bump in `config/app.php` (`'version' => env('APP_VERSION', 'X.Y.Z')`).
2. `vendor/bin/pint --dirty` → passed. `npm run build`. EN/DE-Parität prüfen. AI-Scan (grep changed files).
3. Tests: `php artisan test --teamcity` (Hook erzwingt `--teamcity`). **BUG:** `PhotoEditTest` segfaultet (imagick/GD, „Premature end of PHP process") in vollen Läufen und **maskiert alle danach alphabetisch laufenden Tests**. Workaround: mit `--filter='…'` in Häppchen laufen (z. B. `--filter='Mail|Contact|Settings'` und separat `--filter='Public|Resource|User|Vault'`). „0 failures" aus vollem Lauf ist UNZUVERLÄSSIG. Server-Deploy ist davon unberührt.
4. `php artisan view:cache`. Commit. `git checkout main && git merge --no-ff develop && git tag vX.Y.Z && git push origin main develop --tags`. `gh release create`. Zurück auf `develop`. Dann deployen (s. o.).
- Hotfixes = Patch-Bump (z. B. `1.298.1`).

---

## In DIESER Session gemacht (v1.293 – v1.298.4)

### Audit-Programm abgeschlossen
- **v1.293.0** — letzte 2 Generalisierungen: R-SETTINGS (`Concerns\RedirectsToSettings::savedRedirect()`, 7 Settings-Controller) + R-JS (`app.js` module-level `apiJson()` hinter den 4 identischen `_json`-Helfern). Damit **alle 11 Audit-Generalisierungen** (R-OUT…R-JS) + alle 68 Security-Findings (v1.282–1.293) fertig.

### Mail-Feature-Batch (14 Items, 13 erledigt)
- **v1.294.0** — #9 MIME-Betreff-Dekodierung `=?UTF-8?Q?…?=` (`app/Services/Mail/MimeHeader.php`, iconv+mb-Fallback, guard gegen Doppel-Dekodierung; angewandt auf live reader / .eml-archive / DB-write / archive-summary+search). #12 Kalender-Sidebar `cursor-pointer`. #13 Galerie Live-Photo-Motion nur bei `pointer:fine` (nicht Touch).
- **v1.295.0** — #14 `mail_sync_minutes` global(app_settings)→**per-user** (`user_settings`; Migration seedet je User, droppt alte Spalte; `<meta>` liest per-user). #8 SMTP-Warnung: `MailAccount::smtpConfigured()` gated Send (`mail.smtp_not_configured`), Compose-Banner + Send disabled; echter Transportfehler sichtbar (`mail.send_failed_reason`, `report()`); neues `exclamation-triangle`-Icon.
- **v1.296.0** — #1 größeres Compose-Fenster (`max-w-4xl`) + höherer Editor. #2 To/Cc/Bcc-Autocomplete aus eigenen Kontakten (`GET /mail/recipients?q=`, owner-scoped, ≤10, LIKE-escaped). #3 Editor: Schriftart/-größe/Ausrichtung/Format-löschen. #7 Galerie-Anhang-Picker = Thumbnail-Grid ohne Namen + Server-Suche (Album/Person/Foto), Files-Picker Client-Filter. **Fix 2 Alpine-Crashes** (@paste-Kommentar, `labels.smtpMissingWarning`→statisch).
- **v1.297.0** — #4/#5 **Mehrere Absender-Identitäten pro Konto** (`mail_identities`: from_name/from_email/reply_to/signature/is_default; Migration seedet 1 Default/Konto). CRUD `MailIdentityController` unter `/mail/accounts/{account}/identities` (owner-check ÜBER Parent-Konto, Identitäten haben KEIN user_id; Single-Default transaktional; letzte-Identität-Löschsperre). Compose-Identitäts-Selector setzt From + tauscht Signatur; Senden übergibt `identity_id`. **Fix echter 500 beim Senden**: `MailComposeController` rief `MailAccount::isOwnedBy()` (existiert nicht) → durch `user_id`-Check ersetzt.
- **v1.298.0** — #10 Mail-Reader-Leiste: zwei identische `trash`-Glyphs + einzelner roter Button → move-to-trash=`trash`, permanent-delete=`x-circle`, alles monochrom, in Gruppen mit Trennern neu geordnet. #11 Kontakte ohne Foto → Initialen-Fallback (2 Buchstaben) + neues `user`-Icon. Client-Cache-Flush (`msg:v3`, `msgs:v2`) für dekodierte Betreffe.
- **v1.298.1** — Hotfix: Anhang-Picker-Suchinput nutzte template-context `labels.*` → Crash; auf `@js()`/statisch umgestellt.
- **v1.298.2** — Reply-All eigenes sauberes Icon (`reply-all`) statt 2 überlappende Pfeile; Kontakte-Sidebar Import/Export/Duplikate → gestapelte Text-Links wie Kalender-Sidebar.
- **v1.298.3** — Mail-Settings-Karte von `$admin` nach `$personal` in `settings/index.blade.php` (Sync-Intervall ist per-user; Route war nie admin-gated, nur Karte falsch platziert).
- **v1.298.4** — Alle Settings-Karten (Persönlich + Administration) alphabetisch nach Titel sortiert (`usort` auf lokalisiertem Titel).

---

## NOCH OFFEN / als Nächstes

### Direkt offen aus dem Mail-Batch
- **#6 „Anhang aus Dateien wirft JS-Fehler"** — vom User zurückgestellt (17-Zeilen-Trace nie geliefert). **Wahrscheinlich schon behoben** durch den v1.296 @paste-Fix (der Crash legte die Compose-Komponente lahm, in der der Picker sitzt). → Nach Neustart: einmal „Anhang aus Dateien" testen; falls noch Fehler, Konsolen-Trace holen. Relevanter Code: `openAttachPicker('files')` in `app.js` (~3364), Picker-Modal in `resources/views/mail/index.blade.php` (~596).

### Größere Roadmap (aus Memory, user-bestätigt, pausiert)
Reihenfolge laut `~/.claude/.../memory/ledgerline-mobile-redesign.md`:
1. **Dark Mode mit Toggle**
2. **Vollständige PWA** (aktuell KEIN Service-Worker/Manifest — geprüft)
3. **16-Release Feature-Roadmap** (P0 Security zuerst)

## Memory
Persistenter Kontext liegt in `~/.claude/projects/-Users-malte-kiefer-Entwicklung/memory/` (Index: `MEMORY.md`). Relevant: `ledgerline-mail.md` (kompletter Mail-Verlauf inkl. dieser Session), `ledgerline-audit-2026-07-05.md` (Audit KOMPLETT), `ledgerline-mobile-redesign.md` (Roadmap), `ledgerline-workflow.md`, `ledgerline-docker-selfhost.md` (Deploy-Details), `ledgerline-user-isolation.md`.

## Nützliche Checks
- Icon-Audit (used vs defined): used-Namen aus `x-icon name="…"` gegen die Keys in `icon.blade.php` prüfen — MISSING = leeres SVG.
- Template-`labels.*`-Scan: `grep -rnE '(:[a-z-]+|x-(text|show))="[^"]*labels\.' resources/views/` → muss leer sein.
