<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileOverviewController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\GalleryController as SettingsGalleryController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\TagController as SettingsTagController;
use App\Http\Controllers\VaultController;
use Illuminate\Support\Facades\Route;

// The root simply forwards to the dashboard; unauthenticated visitors are then
// redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('dashboard'));

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
    Route::get('/settings/tags', [SettingsTagController::class, 'index'])->name('settings.tags.index');
    Route::post('/settings/tags', [SettingsTagController::class, 'store'])->name('settings.tags.store');
    Route::put('/settings/tags/{tag}', [SettingsTagController::class, 'update'])->name('settings.tags.update');
    Route::delete('/settings/tags/{tag}', [SettingsTagController::class, 'destroy'])->name('settings.tags.destroy');
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

    // Files: attached to a customer or project; a team-wide overview; download
    // and delete by id.
    Route::get('/files', FileOverviewController::class)->name('files.index');
    Route::post('/files/general', [FileController::class, 'storeGeneral'])->name('files.store.general');
    Route::post('/files/conflicts', [FileController::class, 'conflicts'])->name('files.conflicts');
    Route::post('/files/bulk/download-manifest', [FileController::class, 'downloadManifest'])->name('files.bulk.manifest');
    Route::post('/files/bulk/move', [FileController::class, 'bulkMove'])->name('files.bulk.move');
    Route::post('/files/bulk/delete', [FileController::class, 'bulkDelete'])->name('files.bulk.delete');
    Route::put('/files/{file}/rename', [FileController::class, 'rename'])->name('files.rename');

    // Virtual folders for organising files.
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::put('/folders/{folder}/tags', [FolderController::class, 'updateTags'])->name('folders.tags');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('/files/{file}/extract', [FileController::class, 'extract'])->name('files.extract');
    Route::get('/folders/list', [FolderController::class, 'index'])->name('folders.list');
    Route::get('/folders/{folder}/descendants', [FolderController::class, 'descendants'])->name('folders.descendants');
    Route::put('/files/{file}/encrypt', [FileController::class, 'encrypt'])->name('files.encrypt');
    Route::get('/files/{file}/edit', [FileController::class, 'edit'])->name('files.edit');
    Route::put('/files/{file}/content', [FileController::class, 'updateContent'])->name('files.content');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::put('/files/{file}', [FileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
});
