<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MailAccountController;
use App\Http\Controllers\MailArchiveController;
use App\Http\Controllers\MailReaderController;
use App\Http\Controllers\MailStatsController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\GalleryController as SettingsGalleryController;
use App\Http\Controllers\Settings\MailController as SettingsMailController;
use App\Http\Controllers\Settings\NotificationsController as SettingsNotificationsController;
use App\Http\Controllers\Settings\PaperlessController as SettingsPaperlessController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VaultManifestController;
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
Route::get('/s/{share}', [ShareController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('shares.show');
Route::post('/s/{share}/unlock', [ShareController::class, 'unlock'])
    ->middleware('throttle:10,1')
    ->name('shares.unlock');

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

    // Local in-app notifications (bell menu).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

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

    // Mail settings (accounts + background-sync interval).
    Route::get('/settings/mail', [SettingsMailController::class, 'edit'])->name('settings.mail.edit');
    Route::put('/settings/mail', [SettingsMailController::class, 'update'])->name('settings.mail.update');

    // Paperless-ngx integration (instance URL + API token, cached terms).
    Route::get('/settings/paperless', [SettingsPaperlessController::class, 'edit'])->name('settings.paperless.edit');
    Route::put('/settings/paperless', [SettingsPaperlessController::class, 'update'])->name('settings.paperless.update');
    Route::post('/settings/paperless/test', [SettingsPaperlessController::class, 'test'])->name('settings.paperless.test');
    Route::post('/settings/paperless/sync', [SettingsPaperlessController::class, 'sync'])->name('settings.paperless.sync');

    // Notification channels (mail / NTFY / webhook).
    Route::get('/settings/notifications', [SettingsNotificationsController::class, 'edit'])->name('settings.notifications.edit');
    Route::put('/settings/notifications', [SettingsNotificationsController::class, 'update'])->name('settings.notifications.update');
    Route::post('/settings/notifications/test', [SettingsNotificationsController::class, 'test'])->name('settings.notifications.test');

    // Backup destinations, jobs and run history.
    Route::get('/settings/backup', [SettingsBackupController::class, 'index'])->name('settings.backup.index');
    Route::post('/settings/backup/destinations', [SettingsBackupController::class, 'storeDestination'])->name('settings.backup.destinations.store');
    Route::match(['post', 'put'], '/settings/backup/destinations/test', [SettingsBackupController::class, 'testDestination'])->name('settings.backup.destinations.test');
    Route::put('/settings/backup/destinations/{destination}', [SettingsBackupController::class, 'updateDestination'])->name('settings.backup.destinations.update');
    Route::delete('/settings/backup/destinations/{destination}', [SettingsBackupController::class, 'destroyDestination'])->name('settings.backup.destinations.destroy');
    Route::post('/settings/backup/jobs', [SettingsBackupController::class, 'storeJob'])->name('settings.backup.jobs.store');
    Route::put('/settings/backup/jobs/{job}', [SettingsBackupController::class, 'updateJob'])->name('settings.backup.jobs.update');
    Route::delete('/settings/backup/jobs/{job}', [SettingsBackupController::class, 'destroyJob'])->name('settings.backup.jobs.destroy');
    Route::post('/settings/backup/jobs/{job}/run', [SettingsBackupController::class, 'runNow'])->name('settings.backup.jobs.run');
    Route::get('/settings/backup/runs', [SettingsBackupController::class, 'runs'])->name('settings.backup.runs');
    Route::get('/settings/backup/runs/{run}/download', [SettingsBackupController::class, 'downloadRun'])->name('settings.backup.runs.download');
    Route::post('/settings/backup/runs/{run}/cancel', [SettingsBackupController::class, 'cancelRun'])->name('settings.backup.runs.cancel');

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

    // Files: plain metadata rows + unencrypted bytes on the files disk. The
    // rich client reads/writes the whole tree as a manifest that syncs to rows.
    Route::view('/files', 'files.index')->name('files.index');
    Route::get('/files/data', [FileController::class, 'data'])->name('files.data');
    Route::put('/files/data', [FileController::class, 'sync'])->name('files.sync');
    Route::post('/files/upload', [FileController::class, 'upload'])->name('files.upload');
    Route::post('/files/import', [FileController::class, 'import'])->name('files.import');
    Route::get('/files/raw/{blob}', [FileController::class, 'raw'])->name('files.raw');
    Route::delete('/files/blob/{blob}', [FileController::class, 'deleteBlob'])->name('files.blob.destroy');
    // Notes: plain DB rows, driven client-side over a JSON API (no reloads).
    // Markdown rendering + share creation stay server-side (security-sensitive).
    Route::view('/notes', 'notes.index')->name('notes.index');
    Route::get('/notes/data', [NoteController::class, 'index'])->name('notes.data');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::post('/notes/preview', [NoteController::class, 'preview'])->name('notes.preview');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::patch('/notes/{note}', [NoteController::class, 'patch'])->name('notes.patch');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::delete('/notes/trash/all', [NoteController::class, 'emptyTrash'])->name('notes.trash.empty');
    Route::post('/notes/{note}/share', [NoteController::class, 'share'])->name('notes.share');
    // To-dos: plain DB rows, driven client-side over a JSON API (no reloads).
    Route::view('/todos', 'todos.index')->name('todos.index');
    Route::get('/todos/data', [TodoController::class, 'index'])->name('todos.data');
    Route::post('/todos/lists', [TodoController::class, 'storeList'])->name('todos.lists.store');
    Route::put('/todos/lists/{list}', [TodoController::class, 'updateList'])->name('todos.lists.update');
    Route::delete('/todos/lists/{list}', [TodoController::class, 'destroyList'])->name('todos.lists.destroy');
    Route::post('/todos/tasks', [TodoController::class, 'store'])->name('todos.store');
    Route::put('/todos/tasks/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::patch('/todos/tasks/{todo}', [TodoController::class, 'patch'])->name('todos.patch');
    Route::delete('/todos/tasks/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');
    Route::delete('/todos/trash', [TodoController::class, 'emptyTrash'])->name('todos.trash.empty');
    // Bookmarks: plain DB rows, driven client-side over a JSON API (no reloads).
    Route::view('/bookmarks', 'bookmarks.index')->name('bookmarks.index');
    Route::get('/bookmarks/data', [BookmarkController::class, 'index'])->name('bookmarks.data');
    Route::post('/bookmarks/folders', [BookmarkController::class, 'storeFolder'])->name('bookmarks.folders.store');
    Route::delete('/bookmarks/folders/{folder}', [BookmarkController::class, 'destroyFolder'])->name('bookmarks.folders.destroy');
    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::put('/bookmarks/{bookmark}', [BookmarkController::class, 'update'])->name('bookmarks.update');
    Route::patch('/bookmarks/{bookmark}', [BookmarkController::class, 'patch'])->name('bookmarks.patch');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->name('bookmarks.destroy');
    Route::delete('/bookmarks/trash/all', [BookmarkController::class, 'emptyTrash'])->name('bookmarks.trash.empty');
    Route::view('/mail', 'mail.index')->name('mail.index');
    // Mail accounts: plain rows (password encrypted at rest), JSON API.
    Route::get('/mail/accounts', [MailAccountController::class, 'index'])->name('mail.accounts');
    Route::post('/mail/accounts', [MailAccountController::class, 'store'])->name('mail.accounts.store');
    Route::put('/mail/accounts/{account}', [MailAccountController::class, 'update'])->name('mail.accounts.update');
    Route::delete('/mail/accounts/{account}', [MailAccountController::class, 'destroy'])->name('mail.accounts.destroy');
    Route::post('/mail/stats', [MailStatsController::class, 'show'])->name('mail.stats');
    Route::post('/mail/folders', [MailReaderController::class, 'folders'])->name('mail.folders');
    Route::post('/mail/folder/create', [MailReaderController::class, 'createFolder'])->name('mail.folder.create');
    Route::post('/mail/folder/empty', [MailReaderController::class, 'emptyFolder'])->name('mail.folder.empty');
    Route::post('/mail/messages', [MailReaderController::class, 'messages'])->name('mail.messages');
    Route::post('/mail/message', [MailReaderController::class, 'message'])->name('mail.message');
    Route::post('/mail/message/attachment', [MailReaderController::class, 'attachment'])->name('mail.message.attachment');
    Route::post('/mail/message/action', [MailReaderController::class, 'action'])->name('mail.message.action');
    Route::post('/mail/message/transfer', [MailReaderController::class, 'transfer'])->name('mail.message.transfer');

    // Local mail archive: browse, view, restore (re-append to server), delete.
    Route::get('/mail/archive/{account}', [MailArchiveController::class, 'index'])->name('mail.archive');
    Route::get('/mail/archive/message/{message}', [MailArchiveController::class, 'show'])->name('mail.archive.show');
    Route::get('/mail/archive/message/{message}/attachment/{index}', [MailArchiveController::class, 'attachment'])->whereNumber('index')->name('mail.archive.attachment');
    Route::post('/mail/archive/message/{message}/restore', [MailArchiveController::class, 'restore'])->name('mail.archive.restore');
    Route::delete('/mail/archive/message/{message}', [MailArchiveController::class, 'destroy'])->name('mail.archive.destroy');

    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (shared by mail attachments and the file browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->name('paperless.documents');
    Route::get('/vault/manifest/{name}', [VaultManifestController::class, 'show'])
        ->whereIn('name', ['mail'])->name('vault.manifest.show');
    Route::put('/vault/manifest/{name}', [VaultManifestController::class, 'update'])
        ->whereIn('name', ['mail'])->name('vault.manifest.update');
});
