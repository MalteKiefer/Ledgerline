<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GalleryBlobController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GalleryProcessController;
use App\Http\Controllers\GalleryStoreController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\DownloadsController as SettingsDownloadsController;
use App\Http\Controllers\Settings\FilesController as SettingsFilesController;
use App\Http\Controllers\Settings\NotificationsController as SettingsNotificationsController;
use App\Http\Controllers\Settings\PaperlessController as SettingsPaperlessController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\SystemController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\VaultController;
use Illuminate\Support\Facades\Route;

// The root simply forwards to the dashboard; unauthenticated visitors are then
// redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('dashboard'));

// Guest-only routes: the login page and the Pocket-ID OIDC handshake. The OIDC
// endpoints are throttled to blunt handshake replay/hammering.
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/redirect', [PocketIdController::class, 'redirect'])->middleware('throttle:30,1')->name('auth.redirect');
    Route::get('/auth/callback', [PocketIdController::class, 'callback'])->middleware('throttle:30,1')->name('auth.callback');
});

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');
    // Self-service account: GDPR export, session revocation, account erasure.
    Route::get('/account/export', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('account.export');
    Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->name('account.sessions.revoke');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::post('/profile/avatar/refresh', [AvatarController::class, 'refresh'])->middleware('throttle:6,1')->name('profile.avatar.refresh');

    // QR device pairing: the signed-in owner authorises a new mobile device by
    // approving the code it scanned from the profile page (see routes/api.php).
    Route::post('/device-pairings', [DevicePairingController::class, 'store'])->middleware('throttle:20,1')->name('device-pairings.store');
    Route::get('/device-pairings/{devicePairing}', [DevicePairingController::class, 'show'])->middleware('throttle:120,1')->name('device-pairings.show');
    Route::post('/device-pairings/{devicePairing}/approve', [DevicePairingController::class, 'approve'])->name('device-pairings.approve');
    Route::post('/device-pairings/{devicePairing}/reject', [DevicePairingController::class, 'reject'])->name('device-pairings.reject');
    Route::get('/devices', [DevicePairingController::class, 'devices'])->name('devices.index');
    Route::delete('/devices/{token}', [DevicePairingController::class, 'revokeDevice'])->name('devices.revoke');

    // Local in-app notifications (bell menu).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Settings.
    Route::get('/settings', SettingsController::class)->name('settings');

    // Per-user Files preferences (version-history depth).
    Route::get('/settings/files', [SettingsFilesController::class, 'edit'])->name('settings.files.edit');
    Route::put('/settings/files', [SettingsFilesController::class, 'update'])->name('settings.files.update');

    // Paperless-ngx: per-user integration (each user's own instance URL + token).
    Route::get('/settings/paperless', [SettingsPaperlessController::class, 'edit'])->name('settings.paperless.edit');
    Route::put('/settings/paperless', [SettingsPaperlessController::class, 'update'])->name('settings.paperless.update');
    Route::post('/settings/paperless/test', [SettingsPaperlessController::class, 'test'])->middleware('throttle:20,1')->name('settings.paperless.test');
    Route::post('/settings/paperless/sync', [SettingsPaperlessController::class, 'sync'])->middleware('throttle:20,1')->name('settings.paperless.sync');

    // Non-personal, workspace-wide settings — restricted to the Pocket-ID admin
    // group (config services.pocketid.admin_group; open to all when unset).
    Route::middleware('can:manage-global-settings')->group(function (): void {
        Route::get('/settings/system', [SystemController::class, 'edit'])->name('settings.system.edit');

        // Notification channels (mail / NTFY / webhook).
        Route::get('/settings/notifications', [SettingsNotificationsController::class, 'edit'])->name('settings.notifications.edit');
        Route::put('/settings/notifications', [SettingsNotificationsController::class, 'update'])->name('settings.notifications.update');
        Route::post('/settings/notifications/test', [SettingsNotificationsController::class, 'test'])->middleware('throttle:20,1')->name('settings.notifications.test');

        // Backup destinations, jobs and run history.
        Route::get('/settings/backup', [SettingsBackupController::class, 'index'])->name('settings.backup.index');
        Route::post('/settings/backup/destinations', [SettingsBackupController::class, 'storeDestination'])->name('settings.backup.destinations.store');
        Route::match(['post', 'put'], '/settings/backup/destinations/test', [SettingsBackupController::class, 'testDestination'])->middleware('throttle:20,1')->name('settings.backup.destinations.test');
        Route::put('/settings/backup/destinations/{destination}', [SettingsBackupController::class, 'updateDestination'])->name('settings.backup.destinations.update');
        Route::delete('/settings/backup/destinations/{destination}', [SettingsBackupController::class, 'destroyDestination'])->name('settings.backup.destinations.destroy');
        Route::post('/settings/backup/jobs', [SettingsBackupController::class, 'storeJob'])->name('settings.backup.jobs.store');
        Route::put('/settings/backup/jobs/{job}', [SettingsBackupController::class, 'updateJob'])->name('settings.backup.jobs.update');
        Route::delete('/settings/backup/jobs/{job}', [SettingsBackupController::class, 'destroyJob'])->name('settings.backup.jobs.destroy');
        Route::post('/settings/backup/jobs/{job}/run', [SettingsBackupController::class, 'runNow'])->name('settings.backup.jobs.run');
        Route::get('/settings/backup/runs', [SettingsBackupController::class, 'runs'])->name('settings.backup.runs');
        Route::get('/settings/backup/runs/{run}/download', [SettingsBackupController::class, 'downloadRun'])->name('settings.backup.runs.download');
        Route::post('/settings/backup/runs/{run}/decrypt', [SettingsBackupController::class, 'decryptRun'])->middleware('throttle:10,1')->name('settings.backup.runs.decrypt');
        Route::post('/settings/backup/runs/{run}/cancel', [SettingsBackupController::class, 'cancelRun'])->name('settings.backup.runs.cancel');
    });

    // Downloads/exports: max zip part size (files + gallery) and notify channels.
    Route::middleware('can:manage-global-settings')->group(function (): void {
        Route::get('/settings/downloads', [SettingsDownloadsController::class, 'edit'])->name('settings.downloads.edit');
        Route::put('/settings/downloads', [SettingsDownloadsController::class, 'update'])->name('settings.downloads.update');
    });

    Route::post('/logout', [PocketIdController::class, 'logout'])->name('logout');

    // Zero-knowledge gallery: the client holds all keys and renders entirely
    // from the sealed index + decrypted blobs. The server ships only the shell
    // here; upload/process/blob/store live in the dedicated routes below.
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');

    // Zero-knowledge encryption vault (Files): the server only stores ciphertext
    // and KDF params — never the passphrase, recovery code or vault key.
    Route::get('/vault', [VaultController::class, 'show'])->name('vault.show');
    Route::post('/vault', [VaultController::class, 'store'])->middleware('throttle:10,1')->name('vault.store');
    Route::put('/vault', [VaultController::class, 'rotate'])->middleware('throttle:10,1')->name('vault.rotate');

    // Files: the whole directory tree (names, folders, tags, notes, trash flags,
    // version history) lives in the sealed opaque store; the server only handles
    // the opaque content blobs below (store/stream ciphertext + a quota ledger).
    Route::get('/files', [FileController::class, 'index'])->name('files.index');
    Route::get('/files/usage', [FileController::class, 'usage'])->name('files.usage');
    // Reclaim blobs the (sealed) manifest no longer references — the client sends
    // its live blob set; owner-scoped, grace-gated pruning of the quota ledger.
    Route::post('/files/blobs/reconcile', [FileController::class, 'reconcile'])->middleware('throttle:120,1')->name('files.blobs.reconcile');
    // Throttled to blunt a large-body upload flood (disk-fill / worker-hold),
    // while staying generous enough for a normal batch upload.
    Route::post('/files/upload', [FileController::class, 'upload'])
        ->middleware('throttle:1200,1')->name('files.upload');
    Route::post('/files/upload/init', [FileController::class, 'chunkInit'])->middleware('throttle:600,1')->name('files.upload.init');
    Route::post('/files/upload/part', [FileController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('files.upload.part');
    Route::post('/files/upload/complete', [FileController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('files.upload.complete');
    Route::post('/files/upload/abort', [FileController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('files.upload.abort');
    // Encrypted bytes stream back verbatim; the browser decrypts them. Version
    // history is manifest-side, so a version download is just a raw blob fetch.
    Route::get('/files/raw/{blob}', [FileController::class, 'raw'])->middleware('throttle:600,1')->name('files.raw');
    // Generous limit: emptying a large trash frees hundreds of blobs at once, and
    // each delete is owner-scoped, idempotent and cheap (unlink + ledger row).
    Route::delete('/files/blob/{blob}', [FileController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('files.blob.destroy');

    // Downloads center: asynchronous, worker-built export zips (gallery + files),
    // kept for a retention window and collected here.
    Route::get('/downloads', [DownloadsController::class, 'index'])->name('downloads.index');
    Route::get('/downloads/data', [DownloadsController::class, 'data'])->name('downloads.data');
    Route::get('/downloads/{export}/parts/{index}', [DownloadsController::class, 'download'])
        ->whereNumber('index')->name('downloads.part');
    Route::delete('/downloads', [DownloadsController::class, 'destroy'])->name('downloads.destroy');
    // Notes: plain DB rows, driven client-side over a JSON API (no reloads).
    // Opaque zero-knowledge store: the whole workspace as one sealed manifest.
    Route::get('/store', [StoreController::class, 'show'])->name('store.show');
    Route::put('/store', [StoreController::class, 'save'])->middleware('throttle:600,1')->name('store.save');

    // Opaque zero-knowledge gallery index (photo/album/people structure sealed).
    Route::get('/gallery/store', [GalleryStoreController::class, 'show'])->name('gallery.store.show');
    Route::put('/gallery/store', [GalleryStoreController::class, 'save'])->middleware('throttle:600,1')->name('gallery.store.save');
    // Zero-knowledge transform: the browser POSTs one photo's PLAINTEXT, we return
    // its derived data (renditions/exif/embedding/faces/place) and discard the
    // bytes — nothing is persisted server-side. embed-text embeds a search query.
    Route::post('/gallery/process', [GalleryProcessController::class, 'process'])->middleware('throttle:600,1')->name('gallery.process');
    Route::post('/gallery/embed-text', [GalleryProcessController::class, 'embedText'])->middleware('throttle:300,1')->name('gallery.embed-text');

    // Opaque zero-knowledge gallery content blobs (ciphertext bytes only).
    Route::get('/gallery/usage', [GalleryBlobController::class, 'usage'])->name('gallery.usage');
    Route::post('/gallery/blobs/reconcile', [GalleryBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('gallery.blobs.reconcile');
    Route::post('/gallery/upload', [GalleryBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('gallery.upload');
    Route::post('/gallery/upload/init', [GalleryBlobController::class, 'chunkInit'])->middleware('throttle:600,1')->name('gallery.upload.init');
    Route::post('/gallery/upload/part', [GalleryBlobController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('gallery.upload.part');
    Route::post('/gallery/upload/complete', [GalleryBlobController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('gallery.upload.complete');
    Route::post('/gallery/upload/abort', [GalleryBlobController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('gallery.upload.abort');
    Route::get('/gallery/raw/{blob}', [GalleryBlobController::class, 'raw'])->middleware('throttle:600,1')->name('gallery.raw');
    // Generous limit: emptying a large trash frees hundreds of blobs at once, and
    // each delete is owner-scoped, idempotent and cheap (unlink + ledger row).
    Route::delete('/gallery/blob/{blob}', [GalleryBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('gallery.blob.destroy');

    // Notes live entirely in the zero-knowledge store now; only the page shell
    // remains here (all data flows through GET/PUT /store).
    Route::view('/notes', 'notes.index')->name('notes.index');
    // To-dos: zero-knowledge, living entirely in the opaque store manifest.
    Route::view('/todos', 'todos.index')->name('todos.index');
    // Bookmarks: zero-knowledge, driven client-side from the opaque manifest.
    Route::view('/bookmarks', 'bookmarks.index')->name('bookmarks.index');
    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (shared by mail attachments and the file browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->name('paperless.documents');
});
