<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressBookController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactDuplicateController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FilePublicLinkController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicShareController;
use App\Http\Controllers\ResourceShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\CalendarController as SettingsCalendarController;
use App\Http\Controllers\Settings\ContactsController as SettingsContactsController;
use App\Http\Controllers\Settings\DownloadsController as SettingsDownloadsController;
use App\Http\Controllers\Settings\FilesController as SettingsFilesController;
use App\Http\Controllers\Settings\GalleryController as SettingsGalleryController;
use App\Http\Controllers\Settings\NotificationsController as SettingsNotificationsController;
use App\Http\Controllers\Settings\PaperlessController as SettingsPaperlessController;
use App\Http\Controllers\Settings\RemindersController as SettingsRemindersController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\SystemController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\UploadLinkController;
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

// Public, tokenised links (no account): an ICS feed for a shared calendar and a
// vCard export for a shared address book. No HTML page — the link IS the feed.
Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('/p/{publicShare:token}/ics', [PublicShareController::class, 'ics'])->name('public-share.ics');
    Route::get('/p/{publicShare:token}/vcf', [PublicShareController::class, 'vcf'])->name('public-share.vcf');
    Route::get('/p/{publicShare:token}/album', [PublicShareController::class, 'album'])->name('public-share.album');
    Route::get('/p/{publicShare:token}/photo/{photo}/{size}', [PublicShareController::class, 'photo'])
        ->whereIn('size', ['thumb', 'medium', 'original'])->name('public-share.photo');
    // Password-gated album unlock; throttled to blunt brute-forcing the password.
    Route::post('/p/{publicShare:token}/album/unlock', [PublicShareController::class, 'albumUnlock'])
        ->middleware('throttle:10,1')->name('public-share.album.unlock');
    // Public file download links (token, optional password gate).
    Route::get('/f/{token}', [FilePublicLinkController::class, 'download'])->name('file-link.download');
    Route::post('/f/{token}/unlock', [FilePublicLinkController::class, 'unlock'])->middleware('throttle:10,1')->name('file-link.unlock');
    // Public file-request (upload) links: visitors can only upload.
    Route::get('/u/{token}', [UploadLinkController::class, 'show'])->name('upload-link.show');
    Route::post('/u/{token}/unlock', [UploadLinkController::class, 'unlock'])->middleware('throttle:10,1')->name('upload-link.unlock');
    Route::post('/u/{token}', [UploadLinkController::class, 'upload'])->middleware('throttle:1200,1')->name('upload-link.upload');
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
    Route::get('/settings/calendar', [SettingsCalendarController::class, 'edit'])->name('settings.calendar.edit');
    Route::put('/settings/calendar', [SettingsCalendarController::class, 'update'])->name('settings.calendar.update');
    Route::post('/settings/calendar/refresh-subscriptions', [SettingsCalendarController::class, 'refreshSubscriptions'])->middleware('throttle:10,1')->name('settings.calendar.refresh-subscriptions');

    // Per-user reminder defaults (which channels are pre-selected).
    Route::get('/settings/reminders', [SettingsRemindersController::class, 'edit'])->name('settings.reminders.edit');
    Route::put('/settings/reminders', [SettingsRemindersController::class, 'update'])->name('settings.reminders.update');

    // Per-user Files preferences (version-history depth).
    Route::get('/settings/files', [SettingsFilesController::class, 'edit'])->name('settings.files.edit');
    Route::put('/settings/files', [SettingsFilesController::class, 'update'])->name('settings.files.update');
    Route::post('/settings/files/reindex', [SettingsFilesController::class, 'reindexText'])->middleware('throttle:5,1')->name('settings.files.reindex');

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

    // Contacts / CardDAV: enable + DAV credentials.
    Route::get('/settings/contacts', [SettingsContactsController::class, 'edit'])->name('settings.contacts.edit');
    Route::post('/settings/contacts/credentials', [SettingsContactsController::class, 'generate'])->middleware('throttle:20,1')->name('settings.contacts.generate');
    Route::get('/settings/contacts/profile', [SettingsContactsController::class, 'profile'])->name('settings.contacts.profile');

    // Contacts UI (reload-free JSON over the CardDAV-backed store).
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/data', [ContactController::class, 'data'])->name('contacts.data');
    Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::post('/contacts/import', [ContactController::class, 'import'])->middleware('throttle:30,1')->name('contacts.import');
    Route::post('/contacts/settings', [ContactController::class, 'settings'])->name('contacts.settings');
    Route::get('/contacts/suggest', [ContactController::class, 'suggest'])->name('contacts.suggest');
    // Duplicate review — declared before /contacts/{contact} so "duplicates" is
    // not swallowed by the model-bound show route.
    Route::get('/contacts/duplicates', [ContactDuplicateController::class, 'index'])->middleware('throttle:60,1')->name('contacts.duplicates');
    Route::get('/contacts/duplicates/data', [ContactDuplicateController::class, 'data'])->middleware('throttle:60,1')->name('contacts.duplicates.data');
    Route::post('/contacts/duplicates/merge', [ContactDuplicateController::class, 'merge'])->middleware('throttle:60,1')->name('contacts.duplicates.merge');
    Route::post('/contacts/duplicates/dismiss', [ContactDuplicateController::class, 'dismiss'])->middleware('throttle:60,1')->name('contacts.duplicates.dismiss');
    Route::get('/contacts/new', [ContactController::class, 'create'])->name('contacts.create');
    Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
    Route::get('/contacts/{contact}/view', [ContactController::class, 'view'])->name('contacts.view');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts', [ContactController::class, 'bulkDestroy'])->name('contacts.bulk-destroy');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::get('/contacts/{contact}/avatar', [ContactController::class, 'avatarImage'])->name('contacts.avatar');
    Route::post('/contacts/{contact}/avatar', [ContactController::class, 'avatar'])->name('contacts.avatar.upload');
    Route::patch('/contacts/{contact}/favorite', [ContactController::class, 'favorite'])->name('contacts.favorite');
    Route::get('/contacts/{contact}/geo', [ContactController::class, 'geocode'])->middleware('throttle:30,1')->name('contacts.geo');
    Route::post('/address-books', [AddressBookController::class, 'store'])->name('address-books.store');
    Route::put('/address-books/{addressBook}', [AddressBookController::class, 'update'])->name('address-books.update');
    Route::delete('/address-books/{addressBook}', [AddressBookController::class, 'destroy'])->name('address-books.destroy');
    Route::post('/contact-groups', [ContactGroupController::class, 'store'])->name('contact-groups.store');
    Route::delete('/contact-groups/{group}', [ContactGroupController::class, 'destroy'])->name('contact-groups.destroy');

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
    Route::post('/gallery/location', [GalleryController::class, 'bulkLocation'])->name('gallery.location');
    Route::post('/gallery/download', [GalleryController::class, 'bulkDownload'])->middleware('throttle:6,1')->name('gallery.download');
    Route::post('/gallery/export', [GalleryController::class, 'queueExport'])->middleware('throttle:20,1')->name('gallery.export');
    Route::get('/gallery/{photo}/download/edited', [GalleryController::class, 'downloadEdited'])->name('gallery.download.edited');
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

    // Files: plain metadata rows + unencrypted bytes on the files disk. The
    // rich client reads/writes the whole tree as a manifest that syncs to rows.
    Route::view('/files', 'files.index')->name('files.index');
    Route::get('/files/data', [FileController::class, 'data'])->name('files.data');
    Route::put('/files/data', [FileController::class, 'sync'])->name('files.sync');
    // Throttled to blunt a large-body upload flood (disk-fill / worker-hold),
    // while staying generous enough for a normal batch upload.
    Route::post('/files/upload', [FileController::class, 'upload'])
        ->middleware('throttle:1200,1')->name('files.upload');
    Route::post('/files/upload/init', [FileController::class, 'chunkInit'])->middleware('throttle:600,1')->name('files.upload.init');
    Route::post('/files/upload/part', [FileController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('files.upload.part');
    Route::post('/files/upload/complete', [FileController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('files.upload.complete');
    Route::post('/files/upload/abort', [FileController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('files.upload.abort');
    Route::post('/files/import', [FileController::class, 'import'])
        ->middleware('throttle:300,1')->name('files.import');
    Route::get('/files/raw/{blob}', [FileController::class, 'raw'])->name('files.raw');
    Route::get('/files/thumb/{blob}', [FileController::class, 'thumb'])->name('files.thumb');
    Route::get('/files/search-content', [FileController::class, 'searchContent'])->middleware('throttle:120,1')->name('files.search-content');
    Route::get('/files/upload-links', [UploadLinkController::class, 'index'])->name('files.upload-links.index');
    Route::post('/files/upload-links', [UploadLinkController::class, 'store'])->middleware('throttle:30,1')->name('files.upload-links.store');
    Route::delete('/files/upload-links/{link}', [UploadLinkController::class, 'destroy'])->name('files.upload-links.destroy');
    Route::get('/files/{file}/versions', [FileController::class, 'versions'])->name('files.versions');
    Route::get('/files/{file}/versions/{version}/download', [FileController::class, 'downloadVersion'])->name('files.versions.download');
    Route::post('/files/export', [FileController::class, 'queueExport'])->middleware('throttle:20,1')->name('files.export');
    Route::post('/files/trash', [FileController::class, 'trash'])->middleware('throttle:120,1')->name('files.trash');
    Route::post('/files/restore', [FileController::class, 'restoreTrash'])->middleware('throttle:120,1')->name('files.restore');
    Route::post('/files/favorite', [FileController::class, 'favorite'])->middleware('throttle:120,1')->name('files.favorite');
    Route::post('/files/{file}/note', [FileController::class, 'saveNote'])->middleware('throttle:120,1')->name('files.note');
    Route::get('/files/{file}/public-link', [FilePublicLinkController::class, 'show'])->name('files.public-link.show');
    Route::post('/files/{file}/public-link', [FilePublicLinkController::class, 'store'])->middleware('throttle:30,1')->name('files.public-link.store');
    Route::post('/files/public-link/{link}/rotate', [FilePublicLinkController::class, 'rotate'])->middleware('throttle:30,1')->name('files.public-link.rotate');
    Route::delete('/files/public-link/{link}', [FilePublicLinkController::class, 'destroy'])->name('files.public-link.destroy');
    Route::post('/files/duplicate', [FileController::class, 'duplicate'])->middleware('throttle:60,1')->name('files.duplicate');
    Route::post('/files/bulk-rename', [FileController::class, 'bulkRename'])->middleware('throttle:60,1')->name('files.bulk-rename');
    Route::post('/files/archive', [FileController::class, 'createArchive'])->middleware('throttle:20,1')->name('files.archive');
    Route::post('/files/{file}/extract', [FileController::class, 'extract'])->middleware('throttle:20,1')->name('files.extract');
    Route::get('/files/extract/{token}/status', [FileController::class, 'extractStatus'])->name('files.extract.status');
    Route::delete('/files/blob/{blob}', [FileController::class, 'deleteBlob'])->name('files.blob.destroy');

    // Downloads center: asynchronous, worker-built export zips (gallery + files),
    // kept for a retention window and collected here.
    Route::get('/downloads', [DownloadsController::class, 'index'])->name('downloads.index');
    Route::get('/downloads/data', [DownloadsController::class, 'data'])->name('downloads.data');
    Route::get('/downloads/{export}/parts/{index}', [DownloadsController::class, 'download'])
        ->whereNumber('index')->name('downloads.part');
    Route::delete('/downloads', [DownloadsController::class, 'destroy'])->name('downloads.destroy');
    // Notes: plain DB rows, driven client-side over a JSON API (no reloads).
    // Markdown rendering + share creation stay server-side (security-sensitive).
    Route::view('/notes', 'notes.index')->name('notes.index');
    Route::get('/notes/data', [NoteController::class, 'index'])->name('notes.data');
    Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
    Route::post('/notes/preview', [NoteController::class, 'preview'])->middleware('throttle:60,1')->name('notes.preview');
    Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::patch('/notes/{note}', [NoteController::class, 'patch'])->withTrashed()->name('notes.patch');
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->withTrashed()->name('notes.destroy');
    Route::delete('/notes/trash/all', [NoteController::class, 'emptyTrash'])->name('notes.trash.empty');
    Route::post('/notes/{note}/share', [NoteController::class, 'share'])->middleware('throttle:30,1')->name('notes.share');
    // To-dos: plain DB rows, driven client-side over a JSON API (no reloads).
    Route::view('/todos', 'todos.index')->name('todos.index');
    Route::get('/todos/data', [TodoController::class, 'index'])->name('todos.data');
    Route::post('/todos/lists', [TodoController::class, 'storeList'])->name('todos.lists.store');
    Route::put('/todos/lists/{list}', [TodoController::class, 'updateList'])->name('todos.lists.update');
    Route::delete('/todos/lists/{list}', [TodoController::class, 'destroyList'])->name('todos.lists.destroy');
    Route::post('/todos/tasks', [TodoController::class, 'store'])->name('todos.store');
    Route::put('/todos/tasks/{todo}', [TodoController::class, 'update'])->name('todos.update');
    Route::patch('/todos/tasks/{todo}', [TodoController::class, 'patch'])->withTrashed()->name('todos.patch');
    Route::delete('/todos/tasks/{todo}', [TodoController::class, 'destroy'])->withTrashed()->name('todos.destroy');
    Route::delete('/todos/trash', [TodoController::class, 'emptyTrash'])->name('todos.trash.empty');
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
    // Calendar: CalDAV-backed events, driven client-side over a JSON API.
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/data', [CalendarController::class, 'data'])->name('calendar.data');
    Route::post('/calendar/timezone', [CalendarController::class, 'setTimezone'])->name('calendar.timezone');
    Route::get('/calendar/export', [CalendarController::class, 'export'])->name('calendar.export');
    Route::post('/calendar/import', [CalendarController::class, 'import'])->middleware('throttle:20,1')->name('calendar.import');
    // These issue a server-side outbound fetch → throttle to blunt SSRF-scan /
    // amplification abuse.
    Route::post('/calendar/import-url', [CalendarController::class, 'importUrl'])->middleware('throttle:20,1')->name('calendar.import-url');
    Route::post('/calendar/subscribe', [CalendarController::class, 'subscribe'])->middleware('throttle:20,1')->name('calendar.subscribe');
    Route::post('/calendar/calendars', [CalendarController::class, 'storeCalendar'])->name('calendar.calendars.store');
    Route::put('/calendar/calendars/{calendar}', [CalendarController::class, 'updateCalendar'])->name('calendar.calendars.update');
    Route::delete('/calendar/calendars/{calendar}', [CalendarController::class, 'destroyCalendar'])->name('calendar.calendars.destroy');
    Route::post('/calendar/events', [CalendarController::class, 'store'])->name('calendar.events.store');
    Route::get('/calendar/events/{object}', [CalendarController::class, 'show'])->name('calendar.events.show');
    Route::put('/calendar/events/{object}', [CalendarController::class, 'update'])->name('calendar.events.update');
    Route::patch('/calendar/events/{object}/move', [CalendarController::class, 'move'])->middleware('throttle:120,1')->name('calendar.events.move');
    Route::put('/calendar/events/{object}/instance', [CalendarController::class, 'updateInstance'])->name('calendar.events.instance.update');
    Route::delete('/calendar/events/{object}/instance', [CalendarController::class, 'destroyInstance'])->name('calendar.events.instance.destroy');
    Route::delete('/calendar/events/{object}', [CalendarController::class, 'destroy'])->name('calendar.events.destroy');
    // Bookmarks: plain DB rows, driven client-side over a JSON API (no reloads).
    Route::view('/bookmarks', 'bookmarks.index')->name('bookmarks.index');
    Route::get('/bookmarks/data', [BookmarkController::class, 'index'])->name('bookmarks.data');
    Route::post('/bookmarks/folders', [BookmarkController::class, 'storeFolder'])->name('bookmarks.folders.store');
    Route::delete('/bookmarks/folders/{folder}', [BookmarkController::class, 'destroyFolder'])->name('bookmarks.folders.destroy');
    Route::post('/bookmarks/folders/{folder}/move', [BookmarkController::class, 'moveFolder'])->name('bookmarks.folders.move');
    Route::put('/bookmarks/folders/{folder}', [BookmarkController::class, 'updateFolder'])->name('bookmarks.folders.update');
    Route::post('/bookmarks/{bookmark}/move', [BookmarkController::class, 'moveBookmark'])->name('bookmarks.move');
    Route::get('/bookmarks/export', [BookmarkController::class, 'export'])->name('bookmarks.export');
    Route::post('/bookmarks/import', [BookmarkController::class, 'import'])->middleware('throttle:10,1')->name('bookmarks.import');
    Route::post('/bookmarks/fetch-meta', [BookmarkController::class, 'fetchMeta'])->middleware('throttle:30,1')->name('bookmarks.fetch-meta');
    Route::post('/bookmarks/check-links', [BookmarkController::class, 'checkLinks'])->middleware('throttle:10,1')->name('bookmarks.check-links');
    Route::get('/bookmarks/favicon', [BookmarkController::class, 'favicon'])->middleware('throttle:120,1')->name('bookmarks.favicon');
    Route::post('/bookmarks', [BookmarkController::class, 'store'])->name('bookmarks.store');
    Route::put('/bookmarks/{bookmark}', [BookmarkController::class, 'update'])->name('bookmarks.update');
    Route::patch('/bookmarks/{bookmark}', [BookmarkController::class, 'patch'])->withTrashed()->name('bookmarks.patch');
    Route::delete('/bookmarks/{bookmark}', [BookmarkController::class, 'destroy'])->withTrashed()->name('bookmarks.destroy');
    Route::delete('/bookmarks/trash/all', [BookmarkController::class, 'emptyTrash'])->name('bookmarks.trash.empty');
    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (shared by mail attachments and the file browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->name('paperless.documents');
});
