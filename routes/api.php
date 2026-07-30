<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController as ApiBackupController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\GroupController as ApiGroupController;
use App\Http\Controllers\Api\InvoiceOcrController;
use App\Http\Controllers\Api\PaperlessController as ApiPaperlessController;
use App\Http\Controllers\Api\PasswordController as ApiPasswordController;
use App\Http\Controllers\Api\PublicShareController as ApiPublicShareController;
use App\Http\Controllers\Api\SecurityLogController as ApiSecurityLogController;
use App\Http\Controllers\Api\SettingsController as ApiSettingsController;
use App\Http\Controllers\Api\TwoFactorController as ApiTwoFactorController;
use App\Http\Controllers\Api\UsersController as ApiUsersController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BookmarksController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\GalleryBlobController;
use App\Http\Controllers\GalleryProcessController;
use App\Http\Controllers\GalleryShareController;
use App\Http\Controllers\GalleryStoreController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InvoiceBlobController;
use App\Http\Controllers\InvoicesStoreController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ModuleStoreController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TodosController;
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

        // Plaintext-relational Files core (pivot) — same controller as web, JSON per-record.
        Route::middleware('module:files')->group(function (): void {
            Route::get('/files/trash', [FilesController::class, 'trashed'])->name('api.files.rel.trash');
            Route::get('/files/entries', [FilesController::class, 'index'])->name('api.files.rel.index');
            Route::post('/files/entries', [FilesController::class, 'upload'])->middleware('throttle:1200,1')->name('api.files.rel.upload');
            Route::post('/files/entries/trash/empty', [FilesController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('api.files.rel.empty');
            Route::put('/files/entries/{file}', [FilesController::class, 'update'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.rel.update');
            Route::delete('/files/entries/{file}', [FilesController::class, 'destroy'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.rel.destroy');
            Route::get('/files/entries/{file}/raw', [FilesController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('api.files.rel.raw');
            Route::post('/files/entries/{file}/content', [FilesController::class, 'replaceContent'])->whereNumber('file')->middleware('throttle:1200,1')->name('api.files.rel.content');
            Route::post('/files/entries/{file}/toggle', [FilesController::class, 'toggle'])->whereNumber('file')->middleware('throttle:1200,1')->name('api.files.rel.toggle');
            Route::get('/files/entries/{file}/versions', [FilesController::class, 'versions'])->whereNumber('file')->name('api.files.rel.versions');
            Route::get('/files/entries/{file}/versions/{version}/raw', [FilesController::class, 'versionRaw'])->whereNumber(['file', 'version'])->middleware('throttle:3000,1')->name('api.files.rel.version.raw');
            Route::post('/files/entries/{file}/versions/{version}/restore', [FilesController::class, 'restoreVersion'])->whereNumber(['file', 'version'])->middleware('throttle:600,1')->name('api.files.rel.version.restore');
            Route::post('/files/entries/{id}/restore', [FilesController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.files.rel.restore');
            Route::delete('/files/entries/{id}/force', [FilesController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.files.rel.force');

            Route::get('/files/folders', [FilesController::class, 'folders'])->name('api.files.rel.folders');
            Route::post('/files/folders', [FilesController::class, 'storeFolder'])->middleware('throttle:600,1')->name('api.files.rel.folders.store');
            Route::put('/files/folders/{folder}', [FilesController::class, 'renameFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.files.rel.folders.update');
            Route::post('/files/folders/{folder}/move', [FilesController::class, 'moveFolder'])->whereNumber('folder')->middleware('throttle:1200,1')->name('api.files.rel.folders.move');
            Route::delete('/files/folders/{folder}', [FilesController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.files.rel.folders.destroy');

            Route::post('/files/upload/chunk/init', [FilesController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.files.rel.chunk.init');
            Route::post('/files/upload/chunk/part', [FilesController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.files.rel.chunk.part');
            Route::post('/files/upload/chunk/complete', [FilesController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.files.rel.chunk.complete');
            Route::post('/files/upload/chunk/abort', [FilesController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.files.rel.chunk.abort');
        });

        // Plaintext-relational Notes (pivot Etappe 1) — same controller as web, JSON per-record.
        Route::get('/notes', [NotesController::class, 'index'])->middleware('module:notes')->name('api.notes.index');
        Route::post('/notes', [NotesController::class, 'store'])->middleware(['throttle:600,1', 'module:notes'])->name('api.notes.store');
        Route::put('/notes/{note}', [NotesController::class, 'update'])->whereNumber('note')->middleware(['throttle:600,1', 'module:notes'])->name('api.notes.update');
        Route::delete('/notes/{note}', [NotesController::class, 'destroy'])->whereNumber('note')->middleware(['throttle:600,1', 'module:notes'])->name('api.notes.destroy');
        Route::get('/notes/trash', [NotesController::class, 'trashed'])->middleware('module:notes')->name('api.notes.trash');
        Route::post('/notes/{id}/restore', [NotesController::class, 'restore'])->whereNumber('id')->middleware(['throttle:600,1', 'module:notes'])->name('api.notes.restore');
        Route::delete('/notes/{id}/force', [NotesController::class, 'forceDelete'])->whereNumber('id')->middleware(['throttle:600,1', 'module:notes'])->name('api.notes.force');

        // Plaintext-relational Todos (pivot Etappe 1).
        Route::middleware('module:todos')->group(function (): void {
            Route::get('/todos', [TodosController::class, 'index'])->name('api.todos.index');
            Route::get('/todos/trash', [TodosController::class, 'trashed'])->name('api.todos.trash');
            Route::post('/todos', [TodosController::class, 'store'])->middleware('throttle:600,1')->name('api.todos.store');
            Route::put('/todos/{todo}', [TodosController::class, 'update'])->whereNumber('todo')->middleware('throttle:600,1')->name('api.todos.update');
            Route::post('/todos/{todo}/toggle', [TodosController::class, 'toggle'])->whereNumber('todo')->middleware('throttle:1200,1')->name('api.todos.toggle');
            Route::delete('/todos/{todo}', [TodosController::class, 'destroy'])->whereNumber('todo')->middleware('throttle:600,1')->name('api.todos.destroy');
            Route::post('/todos/{id}/restore', [TodosController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.todos.restore');
            Route::delete('/todos/{id}/force', [TodosController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.todos.force');
            Route::get('/todo-lists', [TodosController::class, 'lists'])->name('api.todos.lists');
            Route::post('/todo-lists', [TodosController::class, 'storeList'])->middleware('throttle:600,1')->name('api.todos.lists.store');
            Route::put('/todo-lists/{list}', [TodosController::class, 'renameList'])->whereNumber('list')->middleware('throttle:600,1')->name('api.todos.lists.rename');
            Route::delete('/todo-lists/{list}', [TodosController::class, 'destroyList'])->whereNumber('list')->middleware('throttle:600,1')->name('api.todos.lists.destroy');
        });

        // Plaintext-relational Bookmarks (pivot Etappe 1).
        Route::middleware('module:bookmarks')->group(function (): void {
            Route::get('/bookmarks', [BookmarksController::class, 'index'])->name('api.bookmarks.index');
            Route::get('/bookmarks/trash', [BookmarksController::class, 'trashed'])->name('api.bookmarks.trash');
            Route::post('/bookmarks', [BookmarksController::class, 'store'])->middleware('throttle:600,1')->name('api.bookmarks.store');
            Route::put('/bookmarks/{bookmark}', [BookmarksController::class, 'update'])->whereNumber('bookmark')->middleware('throttle:600,1')->name('api.bookmarks.update');
            Route::post('/bookmarks/{bookmark}/toggle', [BookmarksController::class, 'toggle'])->whereNumber('bookmark')->middleware('throttle:1200,1')->name('api.bookmarks.toggle');
            Route::post('/bookmarks/{bookmark}/move', [BookmarksController::class, 'move'])->whereNumber('bookmark')->middleware('throttle:1200,1')->name('api.bookmarks.move');
            Route::delete('/bookmarks/{bookmark}', [BookmarksController::class, 'destroy'])->whereNumber('bookmark')->middleware('throttle:600,1')->name('api.bookmarks.destroy');
            Route::post('/bookmarks/{id}/restore', [BookmarksController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.bookmarks.restore');
            Route::delete('/bookmarks/{id}/force', [BookmarksController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.bookmarks.force');
            Route::post('/bookmarks/trash/empty', [BookmarksController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('api.bookmarks.empty');
            Route::get('/bookmark-folders', [BookmarksController::class, 'folders'])->name('api.bookmarks.folders');
            Route::post('/bookmark-folders', [BookmarksController::class, 'storeFolder'])->middleware('throttle:600,1')->name('api.bookmarks.folders.store');
            Route::put('/bookmark-folders/{folder}', [BookmarksController::class, 'updateFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.bookmarks.folders.update');
            Route::post('/bookmark-folders/{folder}/move', [BookmarksController::class, 'moveFolder'])->whereNumber('folder')->middleware('throttle:1200,1')->name('api.bookmarks.folders.move');
            Route::delete('/bookmark-folders/{folder}', [BookmarksController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.bookmarks.folders.destroy');
        });

        // Plaintext-relational Health (pivot) — same controller as web, JSON per-record.
        Route::middleware('module:health')->group(function (): void {
            Route::get('/health/data', [HealthController::class, 'index'])->name('api.health.data');
            Route::put('/health/profile', [HealthController::class, 'saveProfile'])->middleware('throttle:600,1')->name('api.health.profile.save');
            Route::get('/health/entries', [HealthController::class, 'entries'])->name('api.health.entries');
            Route::post('/health/entries', [HealthController::class, 'storeEntry'])->middleware('throttle:600,1')->name('api.health.entries.store');
            Route::put('/health/entries/{entry}', [HealthController::class, 'updateEntry'])->whereNumber('entry')->middleware('throttle:600,1')->name('api.health.entries.update');
            Route::delete('/health/entries/{entry}', [HealthController::class, 'destroyEntry'])->whereNumber('entry')->middleware('throttle:600,1')->name('api.health.entries.destroy');
            Route::get('/health/fasts', [HealthController::class, 'fasts'])->name('api.health.fasts');
            Route::get('/health/fasts/active', [HealthController::class, 'activeFast'])->name('api.health.fasts.active');
            Route::post('/health/fasts', [HealthController::class, 'startFast'])->middleware('throttle:600,1')->name('api.health.fasts.start');
            Route::post('/health/fasts/{fast}/stop', [HealthController::class, 'stopFast'])->whereNumber('fast')->middleware('throttle:600,1')->name('api.health.fasts.stop');
            Route::put('/health/fasts/{fast}', [HealthController::class, 'updateFast'])->whereNumber('fast')->middleware('throttle:600,1')->name('api.health.fasts.update');
            Route::delete('/health/fasts/{fast}', [HealthController::class, 'destroyFast'])->whereNumber('fast')->middleware('throttle:600,1')->name('api.health.fasts.destroy');
        });

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

        // Gallery: sealed index + opaque photo blobs + the stateless transform.
        Route::get('/gallery/store', [GalleryStoreController::class, 'show'])->middleware('module:gallery')->name('api.gallery.store.show');
        Route::put('/gallery/store', [GalleryStoreController::class, 'save'])->middleware(['throttle:120,1', 'module:gallery'])->name('api.gallery.store.save');
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
        Route::post('/gallery/process', [GalleryProcessController::class, 'process'])->middleware('throttle:600,1')->name('api.gallery.process');
        // Deferred vision pass: client POSTs a photo's medium rendition (plaintext, discarded
        // after) and gets back the CLIP embedding + faces to merge into the sealed metadata.
        Route::post('/gallery/analyze', [GalleryProcessController::class, 'analyze'])->middleware('throttle:600,1')->name('api.gallery.analyze');
        Route::post('/gallery/embed-text', [GalleryProcessController::class, 'embedText'])->middleware('throttle:300,1')->name('api.gallery.embed-text');
        // Reverse-geocode a photo coordinate to a place name (viewer display). Self-hosted
        // Photon first (ZK), snap-to-grid before egress, never cached server-side.
        Route::get('/gallery/reverse', [GalleryProcessController::class, 'reverse'])->middleware('throttle:60,1')->name('api.gallery.reverse');
        // Forward geocode: address/place search for photo location tagging (reverse is above).
        Route::get('/gallery/geocode', [GalleryProcessController::class, 'geocode'])->middleware('throttle:60,1')->name('api.gallery.geocode');
        // Album public share links (parity with files.shares): create, update metadata, revoke.
        Route::post('/gallery/shares', [GalleryShareController::class, 'store'])->middleware('throttle:60,1')->name('api.gallery.shares.store');
        Route::put('/gallery/shares/{token}', [GalleryShareController::class, 'update'])->middleware('throttle:60,1')->name('api.gallery.shares.update');
        Route::delete('/gallery/shares/{token}', [GalleryShareController::class, 'destroy'])->middleware('throttle:60,1')->name('api.gallery.shares.destroy');

        // Plaintext-relational Explore (pivot) — same controller as web, JSON.
        // Track point lists are location PII → `encrypted`-cast at rest.
        Route::middleware('module:explore')->group(function (): void {
            Route::get('/explore/data', [ExploreController::class, 'index'])->name('api.explore.data');
            Route::get('/explore/trash', [ExploreController::class, 'trash'])->name('api.explore.trash');
            Route::post('/explore/tracks', [ExploreController::class, 'storeTrack'])->middleware('throttle:600,1')->name('api.explore.tracks.store');
            Route::put('/explore/tracks/{track}', [ExploreController::class, 'updateTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('api.explore.tracks.update');
            Route::delete('/explore/tracks/{track}', [ExploreController::class, 'destroyTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('api.explore.tracks.destroy');
            Route::post('/explore/tracks/{track}/restore', [ExploreController::class, 'restoreTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('api.explore.tracks.restore');
            Route::delete('/explore/tracks/{track}/force', [ExploreController::class, 'forceDeleteTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('api.explore.tracks.force');
            Route::post('/explore/tracks/{track}/file', [ExploreController::class, 'uploadTrackFile'])->whereNumber('track')->middleware('throttle:600,1')->name('api.explore.tracks.file.upload');
            Route::get('/explore/tracks/{track}/file', [ExploreController::class, 'trackFile'])->whereNumber('track')->middleware('throttle:600,1')->name('api.explore.tracks.file');
            Route::post('/explore/couplings', [ExploreController::class, 'setCoupling'])->middleware('throttle:600,1')->name('api.explore.couplings.set');
            Route::delete('/explore/couplings', [ExploreController::class, 'deleteCoupling'])->middleware('throttle:600,1')->name('api.explore.couplings.destroy');
            Route::put('/explore/settings', [ExploreController::class, 'saveSettings'])->middleware('throttle:600,1')->name('api.explore.settings.save');
        });

        // Legacy ZK explore blob endpoints (frontend switch + teardown is a later step):
        // records live in the opaque `explore` module store; these are the raw track blobs.
        // Explore tour-planner auto-routing: snap clicked waypoints to real paths via
        // an OSRM-compatible upstream. SSRF-guarded, coordinates never logged/persisted,
        // clean {geometry:null} when the upstream is unset/unreachable. User-initiated,
        // opt-in egress — same class as /gallery/geocode.
        Route::get('/maps/route', [MapController::class, 'route'])->middleware('throttle:180,1')->name('api.maps.route');
        // Resolve a Google-Maps short link to coordinates for the Explore search.
        // Google-hosts-only egress, link never logged; same opt-in class.
        Route::get('/maps/resolve', [MapController::class, 'resolve'])->middleware('throttle:30,1')->name('api.maps.resolve');

        // Site-icon (BIMI/favicon) proxy: guard-agnostic, SSRF-guarded, nothing
        // stored server-side. Retained for the Finance module (bank logos /
        // partner favicons); the password manager that first used it is removed.
        Route::get('/passwords/icon', [PasswordIconController::class, 'fetch'])->middleware('throttle:1200,1')->name('api.passwords.icon');

        // Connected devices: list, revoke a device's token, request a remote wipe of a
        // lost device (the wipe flag is delivered on that device's next heartbeat).
        // Same guard-agnostic controller as the web routes.
        Route::get('/devices', [DevicePairingController::class, 'devices'])->name('api.devices.index');
        Route::delete('/devices/{token}', [DevicePairingController::class, 'revokeDevice'])->middleware('throttle:20,1')->name('api.devices.revoke');
        Route::post('/devices/{token}/wipe', [DevicePairingController::class, 'wipeDevice'])->middleware('throttle:20,1')->name('api.devices.wipe');
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
