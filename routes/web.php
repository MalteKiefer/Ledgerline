<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LocaleController;
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
use App\Http\Controllers\VaultBlobController;
use App\Http\Controllers\VaultController;
use App\Http\Controllers\VaultManifestController;
use Illuminate\Support\Facades\Route;

// The root simply forwards to the dashboard; unauthenticated visitors are then
// redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('dashboard'));

// Public note share links: no auth and no guest middleware, so a recipient
// without an account can open them and a signed-in user is not redirected
// away. The server renders a frozen snapshot, gated by an optional password.
Route::get('/s/{share}', [ShareController::class, 'show'])->name('shares.show');
Route::post('/s/{share}/unlock', [ShareController::class, 'unlock'])->name('shares.unlock');

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

    // Zero-knowledge file vault: the server stores one encrypted manifest and
    // opaque uuid-keyed blobs; it cannot see names, sizes, structure or counts.
    Route::view('/files', 'files.index')->name('files.index');
    // Notes: plain database rows (not zero-knowledge), rendered server-side.
    Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::post('/notes/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');
    Route::post('/notes/{note}/trash', [NoteController::class, 'trash'])->name('notes.trash');
    Route::post('/notes/{note}/restore', [NoteController::class, 'restore'])->name('notes.restore');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
    Route::delete('/notes/trash/all', [NoteController::class, 'emptyTrash'])->name('notes.trash.empty');
    Route::post('/notes/{note}/share', [NoteController::class, 'share'])->name('notes.share');
    Route::delete('/notes/shares/{share}', [NoteController::class, 'unshare'])->name('notes.unshare');
    // To-dos: plain database rows (not zero-knowledge), rendered server-side
    // with plain form posts. Reminders are managed by the controller.
    Route::get('/todos', [TodoController::class, 'index'])->name('todos.index');
    Route::post('/todos/lists', [TodoController::class, 'storeList'])->name('todos.lists.store');
    Route::put('/todos/lists/{list}', [TodoController::class, 'updateList'])->name('todos.lists.update');
    Route::delete('/todos/lists/{list}', [TodoController::class, 'destroyList'])->name('todos.lists.destroy');
    Route::post('/todos/tasks', [TodoController::class, 'store'])->name('todos.store');
    Route::put('/todos/tasks/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::post('/todos/tasks/{todo}/done', [TodoController::class, 'toggleDone'])->name('todos.done');
    Route::post('/todos/tasks/{todo}/mark', [TodoController::class, 'toggleMark'])->name('todos.mark');
    Route::post('/todos/tasks/{todo}/trash', [TodoController::class, 'trash'])->name('todos.trash');
    Route::post('/todos/tasks/{todo}/restore', [TodoController::class, 'restore'])->name('todos.restore');
    Route::delete('/todos/tasks/{todo}', [TodoController::class, 'destroy'])->name('todos.destroy');
    Route::delete('/todos/trash', [TodoController::class, 'emptyTrash'])->name('todos.trash.empty');
    Route::view('/bookmarks', 'bookmarks.index')->name('bookmarks.index');
    Route::view('/mail', 'mail.index')->name('mail.index');
    Route::post('/mail/stats', [MailStatsController::class, 'show'])->name('mail.stats');
    Route::post('/mail/folders', [MailReaderController::class, 'folders'])->name('mail.folders');
    Route::post('/mail/folder/create', [MailReaderController::class, 'createFolder'])->name('mail.folder.create');
    Route::post('/mail/folder/empty', [MailReaderController::class, 'emptyFolder'])->name('mail.folder.empty');
    Route::post('/mail/messages', [MailReaderController::class, 'messages'])->name('mail.messages');
    Route::post('/mail/message', [MailReaderController::class, 'message'])->name('mail.message');
    Route::post('/mail/message/attachment', [MailReaderController::class, 'attachment'])->name('mail.message.attachment');
    Route::post('/mail/message/action', [MailReaderController::class, 'action'])->name('mail.message.action');
    Route::post('/mail/message/transfer', [MailReaderController::class, 'transfer'])->name('mail.message.transfer');

    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (shared by mail attachments and the file browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->name('paperless.documents');
    Route::get('/vault/manifest/{name}', [VaultManifestController::class, 'show'])
        ->whereIn('name', ['files', 'bookmarks', 'mail'])->name('vault.manifest.show');
    Route::put('/vault/manifest/{name}', [VaultManifestController::class, 'update'])
        ->whereIn('name', ['files', 'bookmarks', 'mail'])->name('vault.manifest.update');
    Route::post('/vault/blobs', [VaultBlobController::class, 'store'])->name('vault.blobs.store');
    Route::get('/vault/blobs/{blob}', [VaultBlobController::class, 'show'])->name('vault.blobs.show');
    Route::delete('/vault/blobs/{blob}', [VaultBlobController::class, 'destroy'])->name('vault.blobs.destroy');
});
