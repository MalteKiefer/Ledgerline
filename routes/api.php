<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ContactBlobController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GalleryBlobController;
use App\Http\Controllers\GalleryProcessController;
use App\Http\Controllers\GalleryStoreController;
use App\Http\Controllers\SharedVaultController;
use App\Http\Controllers\SharedVaultMemberController;
use App\Http\Controllers\SharedVaultStoreController;
use App\Http\Controllers\StoreController;
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
        Route::post('/device/heartbeat', [AuthController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.device.heartbeat');
        Route::delete('/auth/session', [AuthController::class, 'destroy'])->name('api.auth.destroy');

        // Zero-knowledge vault: KDF params + wrapped keys (unlock).
        Route::get('/vault', [VaultController::class, 'show'])->name('api.vault.show');

        // Opaque workspace store: file tree + notes/bookmarks/todos, one sealed manifest.
        Route::get('/store', [StoreController::class, 'show'])->name('api.store.show');
        Route::put('/store', [StoreController::class, 'save'])->middleware('throttle:120,1')->name('api.store.save');

        // Files: opaque content blobs + quota ledger.
        Route::get('/files/usage', [FileController::class, 'usage'])->name('api.files.usage');
        Route::post('/files/blobs/reconcile', [FileController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.files.reconcile');
        Route::post('/files/upload', [FileController::class, 'upload'])->middleware('throttle:1200,1')->name('api.files.upload');
        Route::post('/files/upload/init', [FileController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.files.upload.init');
        Route::post('/files/upload/part', [FileController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.files.upload.part');
        Route::post('/files/upload/complete', [FileController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.files.upload.complete');
        Route::post('/files/upload/abort', [FileController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.files.upload.abort');
        Route::get('/files/raw/{blob}', [FileController::class, 'raw'])->middleware('throttle:600,1')->name('api.files.raw');
        Route::delete('/files/blob/{blob}', [FileController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.files.blob.destroy');

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
        Route::delete('/gallery/blob/{blob}', [GalleryBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.gallery.blob.destroy');
        Route::post('/gallery/process', [GalleryProcessController::class, 'process'])->middleware('throttle:600,1')->name('api.gallery.process');
        // Deferred vision pass: client POSTs a photo's medium rendition (plaintext, discarded
        // after) and gets back the CLIP embedding + faces to merge into the sealed metadata.
        Route::post('/gallery/analyze', [GalleryProcessController::class, 'analyze'])->middleware('throttle:600,1')->name('api.gallery.analyze');
        Route::post('/gallery/embed-text', [GalleryProcessController::class, 'embedText'])->middleware('throttle:300,1')->name('api.gallery.embed-text');
        // Reverse-geocode a photo coordinate to a place name (viewer display). Self-hosted
        // Photon first (ZK), snap-to-grid before egress, never cached server-side.
        Route::get('/gallery/reverse', [GalleryProcessController::class, 'reverse'])->middleware('throttle:60,1')->name('api.gallery.reverse');

        // Contacts: the records themselves live in the workspace manifest above
        // (GET/PUT /store). These are only the optional avatar content blobs, so
        // the native app can show/upload a contact photo. Same controller-reuse,
        // guard-agnostic, zero-knowledge as the web routes.
        Route::get('/contacts/usage', [ContactBlobController::class, 'usage'])->name('api.contacts.usage');
        Route::post('/contacts/blobs/reconcile', [ContactBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('api.contacts.reconcile');
        Route::post('/contacts/upload', [ContactBlobController::class, 'upload'])->middleware('throttle:600,1')->name('api.contacts.upload');
        Route::get('/contacts/raw/{blob}', [ContactBlobController::class, 'raw'])->middleware('throttle:600,1')->name('api.contacts.raw');
        Route::delete('/contacts/blob/{blob}', [ContactBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('api.contacts.blob.destroy');

        // Shared vault-sharing: identity keys, vault containers, sealed manifest
        // stores, and membership management. Same controllers as the web routes —
        // all are guard-agnostic (use $request->user() / Auth::id()).
        Route::prefix('vaults')->name('api.vaults.')->group(function (): void {
            Route::get('/keys', [UserKeyController::class, 'show'])->name('keys.show');
            Route::put('/keys', [UserKeyController::class, 'store'])->name('keys.store');
            Route::post('/', [SharedVaultController::class, 'store'])->name('store');
            Route::get('/', [SharedVaultController::class, 'index'])->name('index');
            Route::post('/{vault}/resolve-recipient', [SharedVaultController::class, 'resolveRecipient'])
                ->middleware('throttle:pubkey-lookup')
                ->name('resolve-recipient');
            Route::get('/{vault}/store', [SharedVaultStoreController::class, 'show'])->name('store.show');
            Route::put('/{vault}/store', [SharedVaultStoreController::class, 'save'])->middleware('throttle:600,1')->name('store.save');
            Route::post('/{vault}/members', [SharedVaultMemberController::class, 'store'])->name('members.store');
            Route::post('/{vault}/members/{member}/accept', [SharedVaultMemberController::class, 'accept'])->name('members.accept');
            Route::patch('/{vault}/members/{member}', [SharedVaultMemberController::class, 'update'])->name('members.update');
            Route::delete('/{vault}/members/{member}', [SharedVaultMemberController::class, 'destroy'])->name('members.destroy');
        });
    });
});
