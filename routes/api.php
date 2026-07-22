<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\ContactBlobController;
use App\Http\Controllers\ContactNotifyController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\ExploreBlobController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileShareController;
use App\Http\Controllers\FilesStoreController;
use App\Http\Controllers\GalleryBlobController;
use App\Http\Controllers\GalleryProcessController;
use App\Http\Controllers\GalleryShareController;
use App\Http\Controllers\GalleryStoreController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\ModuleStoreController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordBreachController;
use App\Http\Controllers\PasswordIconController;
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
        // Streams the signed-in user's avatar (Pocket-ID image, same-origin, non-secret);
        // 404 when none stored. `me.user.has_avatar` tells the app whether to fetch it.
        Route::get('/avatar', AvatarController::class)->middleware('throttle:120,1')->name('api.avatar');
        Route::post('/device/heartbeat', [AuthController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.device.heartbeat');
        Route::delete('/auth/session', [AuthController::class, 'destroy'])->name('api.auth.destroy');

        // Zero-knowledge vault: KDF params + wrapped keys (unlock).
        Route::get('/vault', [VaultController::class, 'show'])->name('api.vault.show');

        // Per-module sealed stores (Store v3 split): one opaque row per module.
        Route::get('/store/{module}', [ModuleStoreController::class, 'show'])->whereAlpha('module')->name('api.module-store.show');
        Route::put('/store/{module}', [ModuleStoreController::class, 'save'])->whereAlpha('module')->middleware('throttle:240,1')->name('api.module-store.save');

        // Files: opaque content blobs + quota ledger.
        Route::get('/files/usage', [FileController::class, 'usage'])->name('api.files.usage');
        // Store v3 (§4.2/A10b): sealed files index (own sharded store, out of the monolith).
        Route::get('/files/store', [FilesStoreController::class, 'show'])->name('api.files.store.show');
        Route::put('/files/store', [FilesStoreController::class, 'save'])->middleware('throttle:120,1')->name('api.files.store.save');
        Route::post('/files/blobs/reconcile', [FileController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.files.reconcile');
        Route::post('/files/upload', [FileController::class, 'upload'])->middleware('throttle:1200,1')->name('api.files.upload');
        Route::post('/files/upload/init', [FileController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.files.upload.init');
        Route::post('/files/upload/part', [FileController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.files.upload.part');
        Route::post('/files/upload/complete', [FileController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.files.upload.complete');
        Route::post('/files/upload/abort', [FileController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.files.upload.abort');
        Route::get('/files/raw/{blob}', [FileController::class, 'raw'])->middleware('throttle:600,1')->name('api.files.raw');
        Route::post('/files/raw-batch', [FileController::class, 'rawBatch'])->middleware('throttle:600,1')->name('api.files.raw-batch');
        Route::delete('/files/blob/{blob}', [FileController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.files.blob.destroy');

        // File / folder public share links: create, update metadata, revoke.
        // Mirrors web routes files.shares.{store|update|destroy} on the mobile API.
        Route::post('/files/shares', [FileShareController::class, 'store'])->middleware('throttle:60,1')->name('api.files.shares.store');
        Route::put('/files/shares/{token}', [FileShareController::class, 'update'])->middleware('throttle:60,1')->name('api.files.shares.update');
        Route::delete('/files/shares/{token}', [FileShareController::class, 'destroy'])->middleware('throttle:60,1')->name('api.files.shares.destroy');

        // Gallery: sealed index + opaque photo blobs + the stateless transform.
        Route::get('/gallery/store', [GalleryStoreController::class, 'show'])->name('api.gallery.store.show');
        Route::put('/gallery/store', [GalleryStoreController::class, 'save'])->middleware('throttle:120,1')->name('api.gallery.store.save');
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

        // Contacts: the records themselves live in the workspace manifest above
        // (GET/PUT /store). These are only the optional avatar content blobs, so
        // the native app can show/upload a contact photo. Same controller-reuse,
        // guard-agnostic, zero-knowledge as the web routes.
        Route::get('/contacts/usage', [ContactBlobController::class, 'usage'])->name('api.contacts.usage');
        Route::post('/contacts/blobs/reconcile', [ContactBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.contacts.reconcile');
        Route::post('/contacts/upload', [ContactBlobController::class, 'upload'])->middleware('throttle:600,1')->name('api.contacts.upload');
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
        Route::get('/maps/route', [MapController::class, 'route'])->middleware('throttle:60,1')->name('api.maps.route');

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
        Route::delete('/devices/{token}', [DevicePairingController::class, 'revokeDevice'])->middleware('throttle:20,1')->name('api.devices.revoke');
        Route::post('/devices/{token}/wipe', [DevicePairingController::class, 'wipeDevice'])->middleware('throttle:20,1')->name('api.devices.wipe');

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

        // Profile avatar refresh from the IdP + persisted locale/theme preference.
        Route::post('/profile/avatar/refresh', [AvatarController::class, 'refresh'])->middleware('throttle:6,1')->name('api.profile.avatar.refresh');
        Route::post('/locale', [LocaleController::class, 'update'])->name('api.locale.update');
        Route::post('/theme', [ThemeController::class, 'update'])->name('api.theme.update');

        // Shared vault-sharing: identity keys, vault containers, sealed manifest
        // stores, and membership management. Same controllers as the web routes —
        // all are guard-agnostic (use $request->user() / Auth::id()).
        Route::prefix('vaults')->name('api.vaults.')->group(function (): void {
            Route::get('/keys', [UserKeyController::class, 'show'])->middleware('throttle:60,1')->name('keys.show');
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
    });
});
