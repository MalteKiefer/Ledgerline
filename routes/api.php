<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController as ApiBackupController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\GroupController as ApiGroupController;
use App\Http\Controllers\Api\InvoiceOcrController;
use App\Http\Controllers\Api\MailAccountController;
use App\Http\Controllers\Api\PaperlessController as ApiPaperlessController;
use App\Http\Controllers\Api\PasswordController as ApiPasswordController;
use App\Http\Controllers\Api\PublicShareController as ApiPublicShareController;
use App\Http\Controllers\Api\SecurityLogController as ApiSecurityLogController;
use App\Http\Controllers\Api\SettingsController as ApiSettingsController;
use App\Http\Controllers\Api\TwoFactorController as ApiTwoFactorController;
use App\Http\Controllers\Api\UsersController as ApiUsersController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\ContactBlobController;
use App\Http\Controllers\ContactNotifyController;
use App\Http\Controllers\ContactsStoreController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\ExploreBlobController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileShareController;
use App\Http\Controllers\FilesStoreController;
use App\Http\Controllers\GalleryBlobController;
use App\Http\Controllers\GalleryProcessController;
use App\Http\Controllers\GalleryShareController;
use App\Http\Controllers\GalleryStoreController;
use App\Http\Controllers\InvoiceBlobController;
use App\Http\Controllers\InvoiceMailController;
use App\Http\Controllers\InvoicesStoreController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MailBlobController;
use App\Http\Controllers\MailMessageController;
use App\Http\Controllers\MailPushbackController;
use App\Http\Controllers\MailTrashController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ModuleStoreController;
use App\Http\Controllers\NoteBlobController;
use App\Http\Controllers\NotesStoreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordBlobController;
use App\Http\Controllers\PasswordBreachController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PasswordsStoreController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\SharedFolderBlobController;
use App\Http\Controllers\SharedVaultController;
use App\Http\Controllers\SharedVaultMemberController;
use App\Http\Controllers\SharedVaultStoreController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TwoFactorDirectoryController;
use App\Http\Controllers\UserKeyController;
use App\Http\Controllers\VaultController;
use App\Http\Middleware\UpdateTokenIp;
use Illuminate\Support\Facades\Route;

/*
 * Mobile API. Versioned under /api/v1; the native app authenticates with a
 * first-party Sanctum bearer obtained via QR device pairing. The data endpoints
 * reuse the web controllers — every payload is opaque ciphertext / a sealed
 * manifest, so the server stays zero-knowledge for the app exactly as it does
 * for the browser. Per-route throttles mirror the web routes.
 */
Route::prefix('v1')->group(function (): void {
    // ─── Public share consumption: no auth, stateless, password → HMAC grant ───
    // The share KEY lives in the URL fragment and never reaches the server.
    // Password-protected shares issue a short-lived HMAC grant on /unlock.
    Route::prefix('s/{token}')->name('api.s.')->group(function (): void {
        Route::get('/meta', [ApiPublicShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
        Route::post('/unlock', [ApiPublicShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
        Route::get('/manifest', [ApiPublicShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
        Route::get('/blob/{ref}', [ApiPublicShareController::class, 'blob'])->middleware('throttle:3000,1')->name('blob');
    });

    // Public pairing exchange — the one-time code is the credential; hard-throttled.
    Route::middleware('throttle:auth-pair')->group(function (): void {
        Route::post('/auth/pair', [AuthController::class, 'pair'])->name('api.auth.pair');
        // Poll for the token via POST so the one-time code travels in the request
        // body, never in a URL/query string (which lands in access logs/proxies).
        Route::post('/auth/pair/collect', [AuthController::class, 'collect'])->name('api.auth.collect');
    });

    // Enforce the scoped 'device' ability minted at pairing (legacy '*' tokens
    // still pass) so a token's declared scope is actually checked.
    Route::middleware(['auth:sanctum', 'abilities:device', UpdateTokenIp::class])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.me');
        // Streams the signed-in user's stored avatar (same-origin, non-secret);
        // 404 when none stored. `me.user.has_avatar` tells the app whether to fetch it.
        Route::get('/avatar', AvatarController::class)->middleware('throttle:120,1')->name('api.avatar');
        Route::post('/device/heartbeat', [AuthController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.device.heartbeat');
        Route::delete('/auth/session', [AuthController::class, 'destroy'])->name('api.auth.destroy');

        // Zero-knowledge vault: KDF params + wrapped keys (unlock).
        Route::get('/vault', [VaultController::class, 'show'])->name('api.vault.show');
        // Vault lifecycle for native clients (no browser): first-time provisioning +
        // passphrase rotation. Carries only wrapped key material + KDF params — never
        // the plaintext vault key (ZK). Same controller as web, throttled like it.
        Route::post('/vault', [VaultController::class, 'store'])->middleware('throttle:10,1')->name('api.vault.store');
        Route::put('/vault', [VaultController::class, 'rotate'])->middleware('throttle:10,1')->name('api.vault.rotate');

        // Per-module sealed stores (Store v3 split): one opaque row per module.
        Route::get('/store/{module}', [ModuleStoreController::class, 'show'])->whereAlpha('module')->middleware('module')->name('api.module-store.show');
        Route::put('/store/{module}', [ModuleStoreController::class, 'save'])->whereAlpha('module')->middleware(['throttle:240,1', 'module'])->name('api.module-store.save');

        // Sealed-root history (recovery net): list retained versions + fetch one to
        // re-merge a dropped record. Read-only, owner-scoped, same module gate.
        Route::get('/store/{module}/history', [ModuleStoreController::class, 'history'])->whereAlpha('module')->middleware('module')->name('api.module-store.history');
        Route::get('/store/{module}/history/{version}', [ModuleStoreController::class, 'historyVersion'])->whereAlpha('module')->whereNumber('version')->middleware('module')->name('api.module-store.history.version');
        Route::get('/files/store/history', [FilesStoreController::class, 'history'])->middleware('module:files')->name('api.files.store.history');
        Route::get('/files/store/history/{version}', [FilesStoreController::class, 'historyVersion'])->whereNumber('version')->middleware('module:files')->name('api.files.store.history.version');
        Route::get('/gallery/store/history', [GalleryStoreController::class, 'history'])->middleware('module:gallery')->name('api.gallery.store.history');
        Route::get('/gallery/store/history/{version}', [GalleryStoreController::class, 'historyVersion'])->whereNumber('version')->middleware('module:gallery')->name('api.gallery.store.history.version');
        Route::get('/notes/store/history', [NotesStoreController::class, 'history'])->middleware('module:notes')->name('api.notes.store.history');
        Route::get('/notes/store/history/{version}', [NotesStoreController::class, 'historyVersion'])->whereNumber('version')->middleware('module:notes')->name('api.notes.store.history.version');
        Route::get('/passwords/store/history', [PasswordsStoreController::class, 'history'])->middleware('module:passwords')->name('api.passwords.store.history');
        Route::get('/passwords/store/history/{version}', [PasswordsStoreController::class, 'historyVersion'])->whereNumber('version')->middleware('module:passwords')->name('api.passwords.store.history.version');
        Route::get('/invoices/store/history', [InvoicesStoreController::class, 'history'])->middleware('module:finance')->name('api.invoices.store.history');
        Route::get('/invoices/store/history/{version}', [InvoicesStoreController::class, 'historyVersion'])->whereNumber('version')->middleware('module:finance')->name('api.invoices.store.history.version');

        // Files: opaque content blobs + quota ledger.
        Route::get('/files/usage', [FileController::class, 'usage'])->name('api.files.usage');
        // Store v3 (§4.2/A10b): sealed files index (own sharded store, out of the monolith).
        Route::get('/files/store', [FilesStoreController::class, 'show'])->middleware('module:files')->name('api.files.store.show');
        Route::put('/files/store', [FilesStoreController::class, 'save'])->middleware(['throttle:120,1', 'module:files'])->name('api.files.store.save');
        Route::post('/files/blobs/reconcile', [FileController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.files.reconcile');
        Route::post('/files/upload', [FileController::class, 'upload'])->middleware('throttle:1200,1')->name('api.files.upload');
        Route::post('/files/upload/init', [FileController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.files.upload.init');
        Route::post('/files/upload/part', [FileController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.files.upload.part');
        Route::post('/files/upload/complete', [FileController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.files.upload.complete');
        Route::post('/files/upload/abort', [FileController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.files.upload.abort');
        Route::get('/files/raw/{blob}', [FileController::class, 'raw'])->middleware('throttle:600,1')->name('api.files.raw');
        Route::post('/files/raw-batch', [FileController::class, 'rawBatch'])->middleware('throttle:600,1')->name('api.files.raw-batch');

        // Notes sharded store (merge-safety spec §3b): sealed root + record-shard blobs.
        Route::get('/notes/store', [NotesStoreController::class, 'show'])->middleware('module:notes')->name('api.notes.store.show');
        Route::put('/notes/store', [NotesStoreController::class, 'save'])->middleware(['throttle:120,1', 'module:notes'])->name('api.notes.store.save');
        Route::post('/notes/upload', [NoteBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('api.notes.upload');
        Route::get('/notes/raw/{blob}', [NoteBlobController::class, 'raw'])->middleware('throttle:600,1')->name('api.notes.raw');
        Route::post('/notes/raw-batch', [NoteBlobController::class, 'rawBatch'])->middleware('throttle:600,1')->name('api.notes.raw-batch');
        Route::post('/notes/blobs/reconcile', [NoteBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.notes.reconcile');

        Route::get('/invoices/store', [InvoicesStoreController::class, 'show'])->middleware('module:finance')->name('api.invoices.store.show');
        Route::put('/invoices/store', [InvoicesStoreController::class, 'save'])->middleware(['throttle:120,1', 'module:finance'])->name('api.invoices.store.save');
        Route::post('/invoices/upload', [InvoiceBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('api.invoices.upload');
        Route::get('/invoices/raw/{blob}', [InvoiceBlobController::class, 'raw'])->middleware('throttle:600,1')->name('api.invoices.raw');
        Route::post('/invoices/raw-batch', [InvoiceBlobController::class, 'rawBatch'])->middleware('throttle:600,1')->name('api.invoices.raw-batch');
        Route::post('/invoices/blobs/reconcile', [InvoiceBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.invoices.reconcile');
        // Transient server-side OCR of a raw (decrypted) receipt: returns line-structured
        // text only (recognition is client-side). Nothing is persisted/logged — same
        // transient-cleartext window as /gallery/process. Best-effort for the client.
        Route::post('/invoices/ocr', [InvoiceOcrController::class, 'ocr'])->middleware(['throttle:120,1', 'module:finance'])->name('api.invoices.ocr');
        Route::post('/invoices/send', [InvoiceMailController::class, 'send'])->middleware(['throttle:30,1', 'module:finance'])->name('api.invoices.send');
        Route::post('/invoices/mail-test', [InvoiceMailController::class, 'test'])->middleware(['throttle:6,1', 'module:finance'])->name('api.invoices.mail-test');

        // Mail archive: account config CRUD + on-demand sync/status + the
        // read-only message ledger, all metadata-only and zero-knowledge preserving —
        // the sealed RFC822 blobs are written directly by the server-side ingestor (no
        // client upload/reconcile route). `module:mail` is enforced (registered in
        // config/modules.php); see MailAccountController's class docblock.
        Route::get('/mail/accounts', [MailAccountController::class, 'index'])->middleware('module:mail')->name('api.mail.accounts.index');
        Route::post('/mail/accounts', [MailAccountController::class, 'store'])->middleware(['throttle:30,1', 'module:mail'])->name('api.mail.accounts.store');
        Route::put('/mail/accounts/{account}', [MailAccountController::class, 'update'])->middleware(['throttle:30,1', 'module:mail'])->name('api.mail.accounts.update');
        Route::delete('/mail/accounts/{account}', [MailAccountController::class, 'destroy'])->middleware(['throttle:30,1', 'module:mail'])->name('api.mail.accounts.destroy');
        Route::post('/mail/accounts/{account}/sync', [MailAccountController::class, 'sync'])->middleware(['throttle:30,1', 'module:mail'])->name('api.mail.accounts.sync');
        Route::post('/mail/accounts/{account}/sync/cancel', [MailAccountController::class, 'cancelSync'])->middleware(['throttle:60,1', 'module:mail'])->name('api.mail.accounts.sync-cancel');
        Route::get('/mail/accounts/{account}/status', [MailAccountController::class, 'status'])->middleware('module:mail')->name('api.mail.accounts.status');
        Route::get('/mail/messages', [MailMessageController::class, 'index'])->middleware(['throttle:120,1', 'module:mail'])->name('api.mail.messages.index');
        Route::post('/mail/messages/{message}/pushback', MailPushbackController::class)->middleware(['throttle:30,1', 'module:mail'])->name('api.mail.messages.pushback');
        Route::post('/mail/messages/trash', [MailTrashController::class, 'trash'])->middleware(['throttle:60,1', 'module:mail'])->name('api.mail.messages.trash');
        Route::post('/mail/messages/restore', [MailTrashController::class, 'restore'])->middleware(['throttle:60,1', 'module:mail'])->name('api.mail.messages.restore');
        Route::get('/mail/raw/{blob}', [MailBlobController::class, 'raw'])->middleware(['throttle:600,1', 'module:mail'])->name('api.mail.raw');

        // Per-user Paperless-ngx integration: cached term quick-picks, live term
        // creation, document forwarding, and cache sync. The /documents endpoint is
        // a transient-cleartext boundary (client posts decrypted bytes; server
        // forwards to the user's own Paperless and stores/logs nothing — same ZK
        // window as /gallery/process and /invoices/ocr).
        Route::get('/paperless/terms', [ApiPaperlessController::class, 'terms'])->middleware('throttle:60,1')->name('api.paperless.terms');
        Route::post('/paperless/terms', [ApiPaperlessController::class, 'createTerm'])->middleware('throttle:30,1')->name('api.paperless.terms.create');
        Route::post('/paperless/documents', [ApiPaperlessController::class, 'submit'])->middleware('throttle:20,1')->name('api.paperless.documents');
        Route::post('/paperless/sync', [ApiPaperlessController::class, 'sync'])->middleware('throttle:20,1')->name('api.paperless.sync');

        // Per-user company profile + invoice defaults (non-secret business identity).
        Route::get('/company', [ApiCompanyController::class, 'show'])->name('api.company.show');
        Route::put('/company', [ApiCompanyController::class, 'update'])->middleware('throttle:60,1')->name('api.company.update');
        Route::get('/company/logo', [ApiCompanyController::class, 'logo'])->middleware('throttle:120,1')->name('api.company.logo');

        // Passwords sharded store (merge-safety spec §3b): sealed root + record-shard blobs.
        Route::get('/passwords/store', [PasswordsStoreController::class, 'show'])->middleware('module:passwords')->name('api.passwords.store.show');
        Route::put('/passwords/store', [PasswordsStoreController::class, 'save'])->middleware(['throttle:120,1', 'module:passwords'])->name('api.passwords.store.save');
        Route::post('/passwords/upload', [PasswordBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('api.passwords.upload');
        Route::get('/passwords/raw/{blob}', [PasswordBlobController::class, 'raw'])->middleware('throttle:600,1')->name('api.passwords.raw');
        Route::post('/passwords/raw-batch', [PasswordBlobController::class, 'rawBatch'])->middleware('throttle:600,1')->name('api.passwords.raw-batch');
        Route::post('/passwords/blobs/reconcile', [PasswordBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.passwords.reconcile');
        Route::delete('/files/blob/{blob}', [FileController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.files.blob.destroy');

        // File / folder public share links: create, update metadata, revoke.
        // Mirrors web routes files.shares.{store|update|destroy} on the mobile API.
        Route::post('/files/shares', [FileShareController::class, 'store'])->middleware('throttle:60,1')->name('api.files.shares.store');
        Route::put('/files/shares/{token}', [FileShareController::class, 'update'])->middleware('throttle:60,1')->name('api.files.shares.update');
        Route::delete('/files/shares/{token}', [FileShareController::class, 'destroy'])->middleware('throttle:60,1')->name('api.files.shares.destroy');

        // Gallery: sealed index + opaque photo blobs + the stateless transform.
        Route::get('/gallery/store', [GalleryStoreController::class, 'show'])->middleware('module:gallery')->name('api.gallery.store.show');
        Route::put('/gallery/store', [GalleryStoreController::class, 'save'])->middleware(['throttle:1200,1', 'module:gallery'])->name('api.gallery.store.save');
        Route::get('/gallery/usage', [GalleryBlobController::class, 'usage'])->name('api.gallery.usage');
        Route::post('/gallery/blobs/reconcile', [GalleryBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.gallery.reconcile');
        Route::post('/gallery/upload', [GalleryBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('api.gallery.upload');
        Route::post('/gallery/upload/init', [GalleryBlobController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.gallery.upload.init');
        Route::post('/gallery/upload/part', [GalleryBlobController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.gallery.upload.part');
        Route::post('/gallery/upload/complete', [GalleryBlobController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.gallery.upload.complete');
        Route::post('/gallery/upload/abort', [GalleryBlobController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.gallery.upload.abort');
        Route::get('/gallery/raw/{blob}', [GalleryBlobController::class, 'raw'])->middleware('throttle:600,1')->name('api.gallery.raw');
        Route::post('/gallery/raw-batch', [GalleryBlobController::class, 'rawBatch'])->middleware('throttle:600,1')->name('api.gallery.raw-batch');
        Route::delete('/gallery/blob/{blob}', [GalleryBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.gallery.blob.destroy');
        Route::post('/gallery/process', [GalleryProcessController::class, 'process'])->middleware('module:gallery', 'throttle:1200,1')->name('api.gallery.process');
        // Deferred vision pass: client POSTs a photo's medium rendition (plaintext, discarded
        // after) and gets back the CLIP embedding + faces to merge into the sealed metadata.
        Route::post('/gallery/analyze', [GalleryProcessController::class, 'analyze'])->middleware('module:gallery', 'throttle:1200,1')->name('api.gallery.analyze');
        Route::post('/gallery/embed-text', [GalleryProcessController::class, 'embedText'])->middleware('module:gallery', 'throttle:300,1')->name('api.gallery.embed-text');
        // Reverse-geocode a photo coordinate to a place name (viewer display). Self-hosted
        // Photon first (ZK), snap-to-grid before egress, never cached server-side.
        Route::get('/gallery/reverse', [GalleryProcessController::class, 'reverse'])->middleware('module:gallery', 'throttle:60,1')->name('api.gallery.reverse');
        // Forward geocode: address/place search for photo location tagging (reverse is above).
        Route::get('/gallery/geocode', [GalleryProcessController::class, 'geocode'])->middleware('module:gallery', 'throttle:60,1')->name('api.gallery.geocode');
        // Album public share links (parity with files.shares): create, update metadata, revoke.
        Route::post('/gallery/shares', [GalleryShareController::class, 'store'])->middleware('throttle:60,1')->name('api.gallery.shares.store');
        Route::put('/gallery/shares/{token}', [GalleryShareController::class, 'update'])->middleware('throttle:60,1')->name('api.gallery.shares.update');
        Route::delete('/gallery/shares/{token}', [GalleryShareController::class, 'destroy'])->middleware('throttle:60,1')->name('api.gallery.shares.destroy');

        // Contacts: the records themselves live in the workspace manifest above
        // (GET/PUT /store). These are only the optional avatar content blobs, so
        // the native app can show/upload a contact photo. Same controller-reuse,
        // guard-agnostic, zero-knowledge as the web routes.
        // Sharded contacts index store (Store v3 §3b): sealed shard-pointer root + history.
        Route::get('/contacts/store', [ContactsStoreController::class, 'show'])->middleware('module:contacts')->name('api.contacts.store.show');
        Route::put('/contacts/store', [ContactsStoreController::class, 'save'])->middleware(['throttle:120,1', 'module:contacts'])->name('api.contacts.store.save');
        Route::get('/contacts/store/history', [ContactsStoreController::class, 'history'])->middleware('module:contacts')->name('api.contacts.store.history');
        Route::get('/contacts/store/history/{version}', [ContactsStoreController::class, 'historyVersion'])->whereNumber('version')->middleware('module:contacts')->name('api.contacts.store.history.version');
        Route::get('/contacts/usage', [ContactBlobController::class, 'usage'])->name('api.contacts.usage');
        Route::post('/contacts/blobs/reconcile', [ContactBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.contacts.reconcile');
        Route::post('/contacts/upload', [ContactBlobController::class, 'upload'])->middleware('throttle:600,1')->name('api.contacts.upload');
        Route::post('/contacts/raw-batch', [ContactBlobController::class, 'rawBatch'])->middleware('throttle:600,1')->name('api.contacts.raw-batch');
        Route::get('/contacts/raw/{blob}', [ContactBlobController::class, 'raw'])->middleware('throttle:600,1')->name('api.contacts.raw');
        Route::delete('/contacts/blob/{blob}', [ContactBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.contacts.blob.destroy');
        // Relay a contact reminder (birthday/anniversary) to the user's own channels.
        Route::post('/contacts/notify', [ContactNotifyController::class, 'send'])->middleware('throttle:60,1')->name('api.contacts.notify');

        // Explore (map/GPS): records live in the opaque `explore` module store
        // (GET/PUT /store/explore); these are only the optional raw track blobs.
        Route::get('/explore/usage', [ExploreBlobController::class, 'usage'])->name('api.explore.usage');
        Route::post('/explore/blobs/reconcile', [ExploreBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.explore.reconcile');
        Route::post('/explore/upload', [ExploreBlobController::class, 'upload'])->middleware('throttle:600,1')->name('api.explore.upload');
        Route::get('/explore/raw/{blob}', [ExploreBlobController::class, 'raw'])->middleware('throttle:600,1')->name('api.explore.raw');
        Route::delete('/explore/blob/{blob}', [ExploreBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.explore.blob.destroy');
        // Explore tour-planner auto-routing: snap clicked waypoints to real paths via
        // an OSRM-compatible upstream. SSRF-guarded, coordinates never logged/persisted,
        // clean {geometry:null} when the upstream is unset/unreachable. User-initiated,
        // opt-in egress — same class as /gallery/geocode.
        Route::get('/maps/route', [MapController::class, 'route'])->middleware('throttle:180,1')->name('api.maps.route');
        // Resolve a Google-Maps short link to coordinates for the Explore search.
        // Google-hosts-only egress, link never logged; same opt-in class.
        Route::get('/maps/resolve', [MapController::class, 'resolve'])->middleware('throttle:30,1')->name('api.maps.resolve');

        // Password enrichment: icon (BIMI/favicon proxy), breach check (HIBP
        // k-anonymity), and 2fa.directory dataset. Same controllers as the web
        // routes — guard-agnostic, SSRF-guarded, nothing stored server-side.
        Route::get('/passwords/icon', [PasswordIconController::class, 'fetch'])->middleware('throttle:1200,1')->name('api.passwords.icon');
        Route::get('/passwords/breach', [PasswordBreachController::class, 'range'])->middleware('throttle:300,1')->name('api.passwords.breach');
        Route::get('/passwords/tfa-directory', [TwoFactorDirectoryController::class, 'index'])->middleware('throttle:120,1')->name('api.passwords.tfa');

        // Connected devices: list, revoke a device's token, request a remote wipe of a
        // lost device (the wipe flag is delivered on that device's next heartbeat).
        // Same guard-agnostic controller as the web routes.
        Route::get('/devices', [DevicePairingController::class, 'devices'])->name('api.devices.index');
        Route::delete('/devices/{token}', [DevicePairingController::class, 'revokeDevice'])->middleware('throttle:60,1')->name('api.devices.revoke');
        Route::post('/devices/{token}/wipe', [DevicePairingController::class, 'wipeDevice'])->middleware('throttle:60,1')->name('api.devices.wipe');
        // Owner-side device pairing (mobile-first user pairs a NEW device): generate a
        // code, poll its state, approve/reject the claim. Owner-scoped (authorizeOwner).
        Route::post('/device-pairings', [DevicePairingController::class, 'store'])->middleware('throttle:20,1')->name('api.device-pairings.store');
        Route::post('/device-pairings/cli', [DevicePairingController::class, 'storeCli'])->middleware('throttle:20,1')->name('api.device-pairings.cli');
        Route::get('/device-pairings/{devicePairing}', [DevicePairingController::class, 'show'])->name('api.device-pairings.show');
        Route::post('/device-pairings/{devicePairing}/approve', [DevicePairingController::class, 'approve'])->middleware('throttle:20,1')->name('api.device-pairings.approve');
        Route::post('/device-pairings/{devicePairing}/reject', [DevicePairingController::class, 'reject'])->middleware('throttle:20,1')->name('api.device-pairings.reject');

        // Notification centre: list (ETag/304), mark one read, mark all read.
        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('api.notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.notifications.read-all');

        // Account: GDPR data export (streamed), account deletion (crypto-shred), and
        // revoking a browser session. The redirect-based web controllers answer with
        // JSON here via expectsJson().
        Route::get('/account/export', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('api.account.export');
        Route::delete('/account', [AccountController::class, 'destroy'])->name('api.account.destroy');
        Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->name('api.account.sessions.revoke');

        Route::post('/locale', [LocaleController::class, 'update'])->name('api.locale.update');
        Route::post('/theme', [ThemeController::class, 'update'])->name('api.theme.update');
        Route::post('/preferences', [PreferencesController::class, 'update'])->name('api.preferences.update');
        // Per-user non-display settings (contact notify channels + file version cap).
        Route::get('/settings', [ApiSettingsController::class, 'show'])->name('api.settings.show');
        Route::put('/settings', [ApiSettingsController::class, 'update'])->middleware('throttle:60,1')->name('api.settings.update');

        // Shared vault-sharing: identity keys, vault containers, sealed manifest
        // stores, and membership management. Same controllers as the web routes —
        // all are guard-agnostic (use $request->user() / Auth::id()).
        Route::prefix('vaults')->name('api.vaults.')->group(function (): void {
            Route::get('/keys', [UserKeyController::class, 'show'])->middleware('throttle:240,1')->name('keys.show');
            Route::put('/keys', [UserKeyController::class, 'store'])->middleware('throttle:30,1')->name('keys.store');
            Route::post('/', [SharedVaultController::class, 'store'])->name('store');
            Route::get('/', [SharedVaultController::class, 'index'])->name('index');
            Route::post('/{vault}/resolve-recipient', [SharedVaultController::class, 'resolveRecipient'])
                ->middleware('throttle:pubkey-lookup')
                ->name('resolve-recipient');
            Route::get('/{vault}/store', [SharedVaultStoreController::class, 'show'])->name('store.show');
            Route::put('/{vault}/store', [SharedVaultStoreController::class, 'save'])->middleware('throttle:600,1')->name('store.save');
            Route::post('/{vault}/members', [SharedVaultMemberController::class, 'store'])->middleware('throttle:30,1')->name('members.store');
            Route::post('/{vault}/members/{member}/accept', [SharedVaultMemberController::class, 'accept'])->middleware('throttle:30,1')->name('members.accept');
            Route::patch('/{vault}/members/{member}', [SharedVaultMemberController::class, 'update'])->middleware('throttle:30,1')->name('members.update');
            Route::delete('/{vault}/members/{member}', [SharedVaultMemberController::class, 'destroy'])->name('members.destroy');
            Route::get('/{vault}/members', [SharedVaultMemberController::class, 'index'])->middleware('throttle:60,1')->name('members.index');
            Route::post('/{vault}/rotate', [SharedVaultController::class, 'rotate'])->middleware('throttle:30,1')->name('rotate');
            Route::delete('/{vault}', [SharedVaultController::class, 'destroy'])->middleware('throttle:30,1')->name('destroy');

            // Shared-folder blob store: member-scoped upload/download/delete/reconcile.
            Route::prefix('{vault}/blobs')->name('blobs.')->group(function (): void {
                Route::get('/usage', [SharedFolderBlobController::class, 'usage'])->name('usage');
                Route::post('/reconcile', [SharedFolderBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('reconcile');
                Route::post('/upload', [SharedFolderBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('upload');
                Route::post('/upload/init', [SharedFolderBlobController::class, 'chunkInit'])->middleware('throttle:600,1')->name('upload.init');
                Route::post('/upload/part', [SharedFolderBlobController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('upload.part');
                Route::post('/upload/complete', [SharedFolderBlobController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('upload.complete');
                Route::post('/upload/abort', [SharedFolderBlobController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('upload.abort');
                Route::get('/raw/{blob}', [SharedFolderBlobController::class, 'raw'])->middleware('throttle:600,1')->name('raw');
                Route::delete('/{blob}', [SharedFolderBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('destroy');
            });
        });

        // 2FA management: enable, QR/secret, confirm, recovery codes, regenerate, disable.
        // Mirrors Fortify's web routes (/user/two-factor-*) for Sanctum bearer clients.
        // Note: Fortify's `confirm => true` applies only to the web guard; no additional
        // password-confirmation step is required here — only the TOTP code (POST confirm).
        Route::prefix('user')->name('api.user.')->group(function (): void {
            Route::prefix('two-factor')->name('2fa.')->group(function (): void {
                Route::post('/enable', [ApiTwoFactorController::class, 'enable'])->middleware('throttle:10,1')->name('enable');
                Route::get('/qr', [ApiTwoFactorController::class, 'qr'])->middleware('throttle:30,1')->name('qr');
                Route::post('/confirm', [ApiTwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
                Route::get('/recovery-codes', [ApiTwoFactorController::class, 'recoveryCodes'])->middleware('throttle:30,1')->name('recovery-codes');
                Route::post('/recovery-codes/regenerate', [ApiTwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:10,1')->name('recovery-codes.regenerate');
                Route::delete('/', [ApiTwoFactorController::class, 'disable'])->middleware('throttle:10,1')->name('disable');
            });

            Route::put('/password', [ApiPasswordController::class, 'update'])->middleware('throttle:10,1')->name('password');
            Route::post('/email/verify/resend', [ApiTwoFactorController::class, 'resendVerification'])->middleware('throttle:6,1')->name('email.verify.resend');
        });

        // Admin group management (workspace-wide limit templates + shareable flag).
        // Gated by the admin role on top of the device token; JSON mirror of the web
        // Settings/GroupsController. Non-secret metadata — zero-knowledge unaffected.
        Route::middleware('can:manage-global-settings')->prefix('groups')->name('api.groups.')->group(function (): void {
            Route::get('/', [ApiGroupController::class, 'index'])->name('index');
            Route::post('/', [ApiGroupController::class, 'store'])->middleware('throttle:60,1')->name('store');
            Route::put('/{group}', [ApiGroupController::class, 'update'])->middleware('throttle:60,1')->name('update');
            Route::delete('/{group}', [ApiGroupController::class, 'destroy'])->middleware('throttle:60,1')->name('destroy');
        });

        // Admin user management API (admin-gated, mirrors web Settings/UsersController).
        Route::middleware('can:manage-global-settings')->prefix('users')->name('api.users.')->group(function (): void {
            Route::get('/', [ApiUsersController::class, 'index'])->name('index');
            Route::post('/', [ApiUsersController::class, 'store'])->middleware('throttle:30,1')->name('store');
            Route::put('/{user}', [ApiUsersController::class, 'update'])->middleware('throttle:60,1')->name('update');
            Route::delete('/{user}', [ApiUsersController::class, 'destroy'])->middleware('throttle:10,1')->name('destroy');
            Route::post('/{user}/reset-password', [ApiUsersController::class, 'resetPassword'])->middleware('throttle:10,1')->name('reset-password');
            Route::post('/{user}/reset-2fa', [ApiUsersController::class, 'resetTwoFactor'])->middleware('throttle:10,1')->name('reset-2fa');
            Route::get('/{user}/avatar', [ApiUsersController::class, 'avatar'])->name('avatar');
            Route::post('/{user}/invite-link', [ApiUsersController::class, 'inviteLink'])->middleware('throttle:20,1')->name('invite-link');
        });

        // Admin security-log API — read-only, metadata-only audit trail.
        // Gated by manage-global-settings (same as web Settings/SecurityLogController).
        Route::middleware('can:manage-global-settings')->prefix('security-log')->name('api.security-log.')->group(function (): void {
            Route::get('/', [ApiSecurityLogController::class, 'index'])->middleware('throttle:60,1')->name('index');
            Route::get('/export', [ApiSecurityLogController::class, 'export'])->middleware('throttle:10,1')->name('export');
        });

        // Admin backup management — JSON mirror of web Settings/BackupController.
        // Gated by the admin role on top of the device token.
        // SECURITY: config (remote credentials) and passphrase (vault-key protection)
        // are encrypted:array / encrypted casts and are NEVER serialised in responses.
        Route::middleware('can:manage-global-settings')->prefix('backup')->name('api.backup.')->group(function (): void {
            // Destinations
            Route::get('/destinations', [ApiBackupController::class, 'destinations'])->name('destinations.index');
            Route::post('/destinations', [ApiBackupController::class, 'storeDestination'])->middleware('throttle:20,1')->name('destinations.store');
            Route::put('/destinations/{destination}', [ApiBackupController::class, 'updateDestination'])->middleware('throttle:20,1')->name('destinations.update');
            Route::delete('/destinations/{destination}', [ApiBackupController::class, 'destroyDestination'])->middleware('throttle:20,1')->name('destinations.destroy');
            Route::post('/destinations/test', [ApiBackupController::class, 'testDestination'])->middleware('throttle:20,1')->name('destinations.test');

            // Jobs
            Route::get('/jobs', [ApiBackupController::class, 'jobs'])->name('jobs.index');
            Route::post('/jobs', [ApiBackupController::class, 'storeJob'])->middleware('throttle:20,1')->name('jobs.store');
            Route::put('/jobs/{job}', [ApiBackupController::class, 'updateJob'])->middleware('throttle:20,1')->name('jobs.update');
            Route::delete('/jobs/{job}', [ApiBackupController::class, 'destroyJob'])->middleware('throttle:20,1')->name('jobs.destroy');
            Route::post('/jobs/{job}/run', [ApiBackupController::class, 'runNow'])->middleware('throttle:10,1')->name('jobs.run');

            // Runs
            Route::get('/runs', [ApiBackupController::class, 'runs'])->name('runs.index');
            Route::get('/runs/{run}/download', [ApiBackupController::class, 'downloadRun'])->name('runs.download');
            Route::post('/runs/{run}/verify', [ApiBackupController::class, 'verifyRun'])->middleware('throttle:10,1')->name('runs.verify');
            Route::post('/runs/{run}/cancel', [ApiBackupController::class, 'cancelRun'])->middleware('throttle:20,1')->name('runs.cancel');
            Route::post('/runs/{run}/decrypt', [ApiBackupController::class, 'decryptRun'])->middleware('throttle:10,1')->name('runs.decrypt');
        });
    });
});
