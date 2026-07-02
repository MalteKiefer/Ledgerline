<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\GalleryController as SettingsGalleryController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\VaultBlobController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VaultManifestController;
use Illuminate\Support\Facades\Route;

// The root simply forwards to the dashboard; unauthenticated visitors are then
// redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('dashboard'));

// Public note share links: no auth and no guest middleware, so a recipient
// without an account can open them and a signed-in user is not redirected
// away. The server only serves ciphertext; the key lives in the fragment.
Route::get('/s/{share}', [ShareController::class, 'show'])->name('shares.show');
Route::get('/s/{share}/data', [ShareController::class, 'data'])->name('shares.data');

// Guest-only routes: the login page and the Pocket-ID OIDC handshake.
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/redirect', [PocketIdController::class, 'redirect'])->name('auth.redirect');
    Route::get('/auth/callback', [PocketIdController::class, 'callback'])->name('auth.callback');
});

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');
    Route::post('/profile/avatar/refresh', [AvatarController::class, 'refresh'])->name('profile.avatar.refresh');

    // Settings.
    Route::get('/settings', SettingsController::class)->name('settings');
    Route::get('/settings/security', [SettingsSecurityController::class, 'edit'])->name('settings.security.edit');
    Route::put('/settings/security', [SettingsSecurityController::class, 'update'])->name('settings.security.update');
    Route::get('/settings/gallery', [SettingsGalleryController::class, 'edit'])->name('settings.gallery.edit');
    Route::put('/settings/gallery', [SettingsGalleryController::class, 'update'])->name('settings.gallery.update');
    Route::post('/settings/gallery/rescan', [SettingsGalleryController::class, 'rescan'])->name('settings.gallery.rescan');
    Route::post('/settings/gallery/regenerate', [SettingsGalleryController::class, 'regenerate'])->name('settings.gallery.regenerate');
    Route::post('/settings/gallery/rename', [SettingsGalleryController::class, 'rename'])->name('settings.gallery.rename');
    Route::post('/settings/gallery/run-all', [SettingsGalleryController::class, 'runAll'])->name('settings.gallery.run-all');
    Route::get('/settings/gallery/queue-status', [SettingsGalleryController::class, 'queueStatus'])->name('settings.gallery.queue-status');
    Route::get('/settings/gallery/batch-status', [SettingsGalleryController::class, 'batchStatus'])->name('settings.gallery.batch-status');
    Route::post('/logout', [PocketIdController::class, 'logout'])->name('logout');

    // Gallery: a photo timeline with drag-and-drop upload and a trash.
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
    Route::post('/gallery', [GalleryController::class, 'store'])->name('gallery.store');
    Route::get('/gallery/feed', [GalleryController::class, 'feed'])->name('gallery.feed');
    Route::get('/gallery/months', [GalleryController::class, 'months'])->name('gallery.months');
    Route::get('/gallery/map', [GalleryController::class, 'map'])->name('gallery.map');
    Route::get('/gallery/trips', [GalleryController::class, 'trips'])->name('gallery.trips');
    Route::get('/gallery/points', [GalleryController::class, 'points'])->name('gallery.points');
    Route::get('/gallery/trash', [GalleryController::class, 'trash'])->name('gallery.trash');
    Route::delete('/gallery', [GalleryController::class, 'destroy'])->name('gallery.destroy');
    Route::post('/gallery/location', [GalleryController::class, 'bulkLocation'])->name('gallery.location');
    Route::post('/gallery/download', [GalleryController::class, 'bulkDownload'])->name('gallery.download');
    Route::get('/gallery/{photo}/download/edited', [GalleryController::class, 'downloadEdited'])->name('gallery.download.edited');
    Route::get('/gallery/geocode/reverse', [GalleryController::class, 'geocodeReverse'])->name('gallery.geocode.reverse');
    Route::get('/gallery/geocode/search', [GalleryController::class, 'geocodeSearch'])->name('gallery.geocode.search');
    Route::put('/gallery/{photo}/meta', [GalleryController::class, 'editMeta'])->name('gallery.meta');
    Route::post('/gallery/{photo}/transform', [GalleryController::class, 'transform'])->name('gallery.transform');
    Route::post('/gallery/{photo}/favorite', [GalleryController::class, 'favorite'])->name('gallery.favorite');
    Route::get('/gallery/{photo}/video', [GalleryController::class, 'video'])->name('gallery.video');
    Route::get('/gallery/{photo}/motion', [GalleryController::class, 'motion'])->name('gallery.motion');
    Route::post('/gallery/trash/restore', [GalleryController::class, 'restore'])->name('gallery.restore');
    Route::delete('/gallery/trash', [GalleryController::class, 'forceDestroy'])->name('gallery.force-destroy');
    Route::get('/gallery/{photo}/{size}', [GalleryController::class, 'image'])
        ->whereIn('size', ['thumb', 'medium', 'original'])->name('gallery.image');

    // Encryption vault (zero-knowledge): the server only stores ciphertext.
    Route::get('/vault', [VaultController::class, 'show'])->name('vault.show');
    Route::post('/vault', [VaultController::class, 'store'])->name('vault.store');
    Route::put('/vault', [VaultController::class, 'rotate'])->name('vault.rotate');

    // Zero-knowledge file vault: the server stores one encrypted manifest and
    // opaque uuid-keyed blobs; it cannot see names, sizes, structure or counts.
    Route::view('/files', 'files.index')->name('files.index');
    Route::view('/notes', 'notes.index')->name('notes.index');
    Route::get('/vault/manifest/{name}', [VaultManifestController::class, 'show'])
        ->whereIn('name', ['files', 'notes'])->name('vault.manifest.show');
    Route::put('/vault/manifest/{name}', [VaultManifestController::class, 'update'])
        ->whereIn('name', ['files', 'notes'])->name('vault.manifest.update');
    Route::post('/vault/blobs', [VaultBlobController::class, 'store'])->name('vault.blobs.store');
    Route::get('/vault/blobs/{blob}', [VaultBlobController::class, 'show'])->name('vault.blobs.show');
    Route::delete('/vault/blobs/{blob}', [VaultBlobController::class, 'destroy'])->name('vault.blobs.destroy');

    // Note sharing: create and revoke time-limited public links (the public
    // show/data endpoints live outside this auth group).
    Route::post('/shares', [ShareController::class, 'store'])->name('shares.store');
    Route::delete('/shares/{share}', [ShareController::class, 'destroy'])->name('shares.destroy');
});
