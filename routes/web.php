<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GalleryBlobController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GalleryStoreController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ResourceShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\DownloadsController as SettingsDownloadsController;
use App\Http\Controllers\Settings\FilesController as SettingsFilesController;
use App\Http\Controllers\Settings\GalleryController as SettingsGalleryController;
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

// Public note share links: no auth and no guest middleware, so a recipient
// without an account can open them and a signed-in user is not redirected
// away. The server renders a frozen snapshot, gated by an optional password.
// Throttled: both endpoints are unauthenticated and do real work (markdown
// render + DB write on show, a bcrypt check on unlock), so a leaked share URL
// must not allow password brute-force or CPU-exhaustion via floods.

// Public, tokenised links (no account): a shared photo album.
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/p/{publicShare:token}/album', [PublicShareController::class, 'album'])->name('public-share.album');
    Route::get('/p/{publicShare:token}/photo/{photo}/{size}', [PublicShareController::class, 'photo'])
        ->whereIn('size', ['thumb', 'medium', 'original'])->name('public-share.photo');
    // Password-gated album unlock; throttled to blunt brute-forcing the password.
    Route::post('/p/{publicShare:token}/album/unlock', [PublicShareController::class, 'albumUnlock'])
        ->middleware('throttle:10,1')->name('public-share.album.unlock');
    // No public file-download or upload-request links: they'd need the server to
    // read/produce plaintext, which the zero-knowledge vault forbids.
});

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
    Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:60,1')->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->middleware('throttle:120,1')->name('search.suggest');
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');
    // Self-service account: GDPR export, session revocation, account erasure.
    Route::get('/account/export', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('account.export');
    Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->name('account.sessions.revoke');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::post('/profile/avatar/refresh', [AvatarController::class, 'refresh'])->middleware('throttle:6,1')->name('profile.avatar.refresh');

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
        Route::get('/settings/gallery', [SettingsGalleryController::class, 'edit'])->name('settings.gallery.edit');
        Route::put('/settings/gallery', [SettingsGalleryController::class, 'update'])->name('settings.gallery.update');
        Route::post('/settings/gallery/rescan', [SettingsGalleryController::class, 'rescan'])->name('settings.gallery.rescan');
        Route::post('/settings/gallery/regenerate', [SettingsGalleryController::class, 'regenerate'])->name('settings.gallery.regenerate');
        Route::post('/settings/gallery/rename', [SettingsGalleryController::class, 'rename'])->name('settings.gallery.rename');
        Route::post('/settings/gallery/run-all', [SettingsGalleryController::class, 'runAll'])->name('settings.gallery.run-all');
        Route::post('/settings/gallery/detect-duplicates', [SettingsGalleryController::class, 'detectDuplicates'])->name('settings.gallery.detect-duplicates');
        Route::post('/settings/gallery/detect-faces', [SettingsGalleryController::class, 'detectFaces'])->name('settings.gallery.detect-faces');
        Route::get('/settings/gallery/queue-status', [SettingsGalleryController::class, 'queueStatus'])->name('settings.gallery.queue-status');
        Route::get('/settings/gallery/batch-status', [SettingsGalleryController::class, 'batchStatus'])->name('settings.gallery.batch-status');

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

    // Gallery: a photo timeline with drag-and-drop upload and a trash.
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
    // Throttled: each accepted upload dispatches heavy media processing.
    Route::post('/gallery', [GalleryController::class, 'store'])->middleware('throttle:300,1')->name('gallery.store');
    Route::get('/gallery/feed', [GalleryController::class, 'feed'])->name('gallery.feed');
    Route::get('/gallery/months', [GalleryController::class, 'months'])->name('gallery.months');
    Route::get('/gallery/map', [GalleryController::class, 'map'])->name('gallery.map');
    Route::get('/gallery/trips', [GalleryController::class, 'trips'])->name('gallery.trips');
    Route::get('/gallery/points', [GalleryController::class, 'points'])->name('gallery.points');
    Route::get('/gallery/picker', [GalleryController::class, 'pickerList'])->name('gallery.picker');
    Route::post('/gallery/columns', [GalleryController::class, 'setColumns'])->name('gallery.columns');

    // Albums (before /gallery/{...} model-bound routes).
    Route::get('/gallery/albums', [AlbumController::class, 'index'])->name('gallery.albums');
    Route::get('/gallery/albums/data', [AlbumController::class, 'data'])->name('gallery.albums.data');
    Route::post('/gallery/albums', [AlbumController::class, 'store'])->name('gallery.albums.store');
    Route::get('/gallery/albums/{album}', [AlbumController::class, 'show'])->name('gallery.albums.show');
    Route::get('/gallery/albums/{album}/data', [AlbumController::class, 'showData'])->name('gallery.albums.show.data');
    Route::put('/gallery/albums/{album}', [AlbumController::class, 'update'])->name('gallery.albums.update');
    Route::delete('/gallery/albums/{album}', [AlbumController::class, 'destroy'])->name('gallery.albums.destroy');
    Route::post('/gallery/albums/{album}/photos', [AlbumController::class, 'addPhotos'])->name('gallery.albums.photos.add');
    Route::delete('/gallery/albums/{album}/photos', [AlbumController::class, 'removePhotos'])->name('gallery.albums.photos.remove');
    Route::get('/gallery/trash', [GalleryController::class, 'trash'])->name('gallery.trash');
    // Duplicates: content-based duplicate groups; keep one, trash the rest.
    Route::get('/gallery/duplicates', [GalleryController::class, 'duplicates'])->name('gallery.duplicates');
    Route::get('/gallery/duplicates/data', [GalleryController::class, 'duplicatesData'])->name('gallery.duplicates.data');
    Route::post('/gallery/duplicates/{group}/resolve', [GalleryController::class, 'resolveDuplicate'])->name('gallery.duplicates.resolve');
    Route::post('/gallery/duplicates/{group}/dismiss', [GalleryController::class, 'dismissDuplicate'])->name('gallery.duplicates.dismiss');
    // People: faces clustered into people; name/merge/hide/reassign.
    Route::get('/gallery/people', [PersonController::class, 'index'])->name('gallery.people');
    Route::get('/gallery/people/data', [PersonController::class, 'data'])->name('gallery.people.data');
    Route::get('/gallery/faces/{face}/thumb', [PersonController::class, 'thumb'])->name('gallery.faces.thumb');
    Route::post('/gallery/faces/{face}/reassign', [PersonController::class, 'reassignFace'])->name('gallery.faces.reassign');
    Route::get('/gallery/people/{person}', [PersonController::class, 'show'])->name('gallery.people.show');
    Route::get('/gallery/people/{person}/data', [PersonController::class, 'showData'])->name('gallery.people.show.data');
    Route::patch('/gallery/people/{person}', [PersonController::class, 'update'])->name('gallery.people.update');
    Route::post('/gallery/people/{person}/merge', [PersonController::class, 'merge'])->name('gallery.people.merge');
    Route::delete('/gallery', [GalleryController::class, 'destroy'])->name('gallery.destroy');
    Route::delete('/gallery/all', [GalleryController::class, 'destroyAll'])->middleware('throttle:6,1')->name('gallery.destroy-all');
    Route::post('/gallery/location', [GalleryController::class, 'bulkLocation'])->name('gallery.location');
    Route::post('/gallery/download', [GalleryController::class, 'bulkDownload'])->middleware('throttle:6,1')->name('gallery.download');
    Route::post('/gallery/export', [GalleryController::class, 'queueExport'])->middleware('throttle:20,1')->name('gallery.export');
    Route::get('/gallery/{photo}/download/edited', [GalleryController::class, 'downloadEdited'])->middleware('throttle:30,1')->name('gallery.download.edited');
    // Throttled: each call blocks on a global geocoder lock + a slow outbound
    // request, so it can pin PHP-FPM workers without a limit.
    Route::get('/gallery/geocode/reverse', [GalleryController::class, 'geocodeReverse'])->middleware('throttle:20,1')->name('gallery.geocode.reverse');
    Route::get('/gallery/geocode/search', [GalleryController::class, 'geocodeSearch'])->middleware('throttle:20,1')->name('gallery.geocode.search');
    Route::put('/gallery/{photo}/meta', [GalleryController::class, 'editMeta'])->name('gallery.meta');
    Route::post('/gallery/{photo}/transform', [GalleryController::class, 'transform'])->name('gallery.transform');
    Route::post('/gallery/{photo}/favorite', [GalleryController::class, 'favorite'])->name('gallery.favorite');
    Route::get('/gallery/{photo}/video', [GalleryController::class, 'video'])->name('gallery.video');
    Route::get('/gallery/{photo}/motion', [GalleryController::class, 'motion'])->name('gallery.motion');
    Route::post('/gallery/trash/restore', [GalleryController::class, 'restore'])->name('gallery.restore');
    Route::delete('/gallery/trash', [GalleryController::class, 'forceDestroy'])->name('gallery.force-destroy');
    Route::get('/gallery/{photo}/{size}', [GalleryController::class, 'image'])
        ->whereIn('size', ['thumb', 'medium', 'original'])->name('gallery.image');

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
    Route::delete('/files/blob/{blob}', [FileController::class, 'deleteBlob'])->middleware('throttle:120,1')->name('files.blob.destroy');

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
    Route::put('/store', [StoreController::class, 'save'])->middleware('throttle:120,1')->name('store.save');

    // Opaque zero-knowledge gallery index (photo/album/people structure sealed).
    Route::get('/gallery/store', [GalleryStoreController::class, 'show'])->name('gallery.store.show');
    Route::put('/gallery/store', [GalleryStoreController::class, 'save'])->middleware('throttle:120,1')->name('gallery.store.save');

    // Opaque zero-knowledge gallery content blobs (ciphertext bytes only).
    Route::get('/gallery/usage', [GalleryBlobController::class, 'usage'])->name('gallery.usage');
    Route::post('/gallery/blobs/reconcile', [GalleryBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('gallery.blobs.reconcile');
    Route::post('/gallery/upload', [GalleryBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('gallery.upload');
    Route::post('/gallery/upload/init', [GalleryBlobController::class, 'chunkInit'])->middleware('throttle:600,1')->name('gallery.upload.init');
    Route::post('/gallery/upload/part', [GalleryBlobController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('gallery.upload.part');
    Route::post('/gallery/upload/complete', [GalleryBlobController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('gallery.upload.complete');
    Route::post('/gallery/upload/abort', [GalleryBlobController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('gallery.upload.abort');
    Route::get('/gallery/raw/{blob}', [GalleryBlobController::class, 'raw'])->middleware('throttle:600,1')->name('gallery.raw');
    Route::delete('/gallery/blob/{blob}', [GalleryBlobController::class, 'deleteBlob'])->middleware('throttle:120,1')->name('gallery.blob.destroy');

    // Notes live entirely in the zero-knowledge store now; only the page shell
    // remains here (all data flows through GET/PUT /store).
    Route::view('/notes', 'notes.index')->name('notes.index');
    // To-dos: zero-knowledge, living entirely in the opaque store manifest.
    Route::view('/todos', 'todos.index')->name('todos.index');
    // Cross-user sharing management (grant/revoke access to your resources).
    Route::get('/shares/data', [ResourceShareController::class, 'data'])->name('shares.data');
    Route::post('/shares', [ResourceShareController::class, 'store'])->name('shares.store');
    Route::delete('/shares/{share}', [ResourceShareController::class, 'destroy'])->name('shares.destroy');
    Route::post('/shares/{share}/email', [ResourceShareController::class, 'email'])->middleware('throttle:5,1')->name('shares.email');
    // Public (external) tokenised links.
    Route::post('/shares/public', [PublicShareController::class, 'store'])->name('public-share.store');
    Route::delete('/shares/public/{publicShare}', [PublicShareController::class, 'destroy'])->name('public-share.destroy');
    Route::post('/shares/public/{publicShare}/email', [PublicShareController::class, 'email'])->middleware('throttle:5,1')->name('public-share.email');
    Route::post('/shares/public/{publicShare}/rotate', [PublicShareController::class, 'rotate'])->name('public-share.rotate');
    // Bookmarks: zero-knowledge, driven client-side from the opaque manifest.
    Route::view('/bookmarks', 'bookmarks.index')->name('bookmarks.index');
    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (shared by mail attachments and the file browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->name('paperless.documents');
});
