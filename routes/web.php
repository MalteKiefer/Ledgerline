<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BookmarksController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GalleryProcessController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicFileShareController;
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\CompanyController as SettingsCompanyController;
use App\Http\Controllers\Settings\FilesController as SettingsFilesController;
use App\Http\Controllers\Settings\GroupsController as SettingsGroupsController;
use App\Http\Controllers\Settings\NotificationsController as SettingsNotificationsController;
use App\Http\Controllers\Settings\PaperlessController as SettingsPaperlessController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\Settings\SecurityLogController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\SystemController;
use App\Http\Controllers\Settings\UsersController as SettingsUsersController;
use App\Http\Controllers\SharedFolderController;
use App\Http\Controllers\SharedWithMeController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TodosController;
use Illuminate\Support\Facades\Route;

// The root simply forwards to the dashboard; unauthenticated visitors are then
// redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('dashboard'));

// Prometheus metrics for external scraping — no session; guarded by its own
// token (OPS_METRICS_TOKEN) and disabled when unset. Rate-limited.
Route::get('/metrics', [MetricsController::class, 'index'])->middleware('throttle:60,1')->name('metrics');

// Public, unauthenticated PLAINTEXT gallery share links (pivot). Bytes are served
// in the clear (no fragment key) with an optional rate-limited password gate.
// Owner-scoped by
// token; a photo raw is only streamed for a valid (unexpired, unlocked) share.
Route::prefix('gallery-share/{token}')->name('public.gallery-share.')->group(function (): void {
    Route::get('/', [GalleryController::class, 'shareMeta'])->middleware('throttle:120,1')->name('meta');
    Route::post('/unlock', [GalleryController::class, 'shareUnlock'])->middleware('throttle:10,1')->name('unlock');
    Route::get('/manifest', [GalleryController::class, 'shareManifest'])->middleware('throttle:120,1')->name('manifest');
    Route::get('/photo/{photo}/raw', [GalleryController::class, 'sharePhotoRaw'])->whereNumber('photo')->middleware('throttle:3000,1')->name('photo.raw');
});

// Public, unauthenticated PLAINTEXT Files share links (pivot). A shared file or
// folder subtree; bytes served plaintext with an optional rate-limited password
// gate. Owner-scoped by token; a file raw is only streamed for a valid
// (unexpired, unlocked) share whose subtree contains that file.
Route::prefix('file-share/{token}')->name('public.file-share.')->group(function (): void {
    Route::get('/', [PublicFileShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
    Route::post('/unlock', [PublicFileShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
    Route::get('/manifest', [PublicFileShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
    Route::get('/file/{file}/raw', [PublicFileShareController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('file.raw');
});

// First-party auth (login, registration, password reset, email verification,
// two-factor) is owned by Laravel Fortify — see FortifyServiceProvider.

// Mail-independent invite / password-reset links: public consumption. The token
// is a hashed, single-use, expiring secret in the URL; the route is throttled and
// verifies it in constant time. Consuming it sets the user's password.
Route::get('/invite/{invite}/{token}', [InviteLinkController::class, 'show'])->middleware('throttle:20,1')->name('invite.show');
Route::post('/invite/{invite}/{token}', [InviteLinkController::class, 'store'])->middleware('throttle:10,1')->name('invite.store');

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->middleware('module:dashboard')->name('dashboard');
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
    Route::post('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    // Profile = the iOS-style personal hub; each section is its own sub-page.
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::get('/profile/account', [ProfileController::class, 'account'])->name('profile.account');
    Route::get('/profile/devices', [ProfileController::class, 'devices'])->name('profile.devices');
    Route::get('/profile/sessions', [ProfileController::class, 'sessions'])->name('profile.sessions');
    Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');
    Route::get('/profile/appearance', [ProfileController::class, 'appearance'])->name('profile.appearance');
    Route::get('/profile/export', [ProfileController::class, 'exportPage'])->name('profile.export');
    Route::get('/profile/danger', [ProfileController::class, 'danger'])->name('profile.danger');
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');
    // Self-service account: GDPR export, session revocation, account erasure.
    Route::get('/account/export', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('account.export');
    Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->name('account.sessions.revoke');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    // QR device pairing: the signed-in owner authorises a new mobile device by
    // approving the code it scanned from the profile page (see routes/api.php).
    Route::post('/device-pairings', [DevicePairingController::class, 'store'])->middleware('throttle:60,1')->name('device-pairings.store');
    // Copy/paste pairing for the command-line client — same state machine, code shown as text.
    Route::post('/device-pairings/cli', [DevicePairingController::class, 'storeCli'])->middleware('throttle:60,1')->name('device-pairings.store-cli');
    Route::get('/device-pairings/{devicePairing}', [DevicePairingController::class, 'show'])->middleware('throttle:120,1')->name('device-pairings.show');
    Route::post('/device-pairings/{devicePairing}/approve', [DevicePairingController::class, 'approve'])->name('device-pairings.approve');
    Route::post('/device-pairings/{devicePairing}/reject', [DevicePairingController::class, 'reject'])->name('device-pairings.reject');
    Route::get('/devices', [DevicePairingController::class, 'devices'])->name('devices.index');
    Route::delete('/devices/{token}', [DevicePairingController::class, 'revokeDevice'])->middleware('throttle:20,1')->name('devices.revoke');
    Route::post('/devices/{token}/wipe', [DevicePairingController::class, 'wipeDevice'])->middleware('throttle:20,1')->name('devices.wipe');

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

    // Non-personal, workspace-wide settings — restricted to users with the admin
    // role (see User::managesGlobalSettings / the manage-global-settings gate).
    Route::middleware('can:manage-global-settings')->group(function (): void {
        // Workspace-wide file limits (quota, max upload, orphan grace). The
        // per-user version-keep count stays on settings.files.edit (profile hub).
        Route::get('/settings/files/limits', [SettingsFilesController::class, 'limits'])->name('settings.files.limits');
        Route::put('/settings/files/limits', [SettingsFilesController::class, 'limitsUpdate'])->name('settings.files.limits.update');

        Route::get('/settings/system', [SystemController::class, 'edit'])->name('settings.system.edit');
        Route::post('/settings/system/errors/{error}/resolve', [SystemController::class, 'resolveError'])->name('settings.system.errors.resolve');

        // Security log: filterable audit trail + CSV/JSON export (admin only).
        Route::get('/settings/security-log', [SecurityLogController::class, 'index'])->name('settings.security-log');

        // User management: list, create, edit role + per-user limits, reset, delete.
        Route::get('/settings/users', [SettingsUsersController::class, 'index'])->name('settings.users');
        Route::post('/settings/users', [SettingsUsersController::class, 'store'])->name('settings.users.store');
        Route::put('/settings/users/{user}', [SettingsUsersController::class, 'update'])->name('settings.users.update');
        Route::post('/settings/users/{user}/reset-password', [SettingsUsersController::class, 'resetPassword'])->middleware('throttle:10,1')->name('settings.users.reset');
        Route::post('/settings/users/{user}/reset-2fa', [SettingsUsersController::class, 'resetTwoFactor'])->name('settings.users.reset2fa');
        Route::post('/settings/users/{user}/invite-link', [InviteLinkController::class, 'create'])->middleware('throttle:20,1')->name('settings.users.invite');
        Route::get('/settings/users/{user}/avatar', [SettingsUsersController::class, 'avatar'])->name('settings.users.avatar');
        Route::delete('/settings/users/{user}', [SettingsUsersController::class, 'destroy'])->name('settings.users.destroy');
        Route::post('/settings/registration', [SettingsUsersController::class, 'registration'])->name('settings.registration');

        // Group management: reusable limit templates + shareable flag.
        Route::get('/settings/groups', [SettingsGroupsController::class, 'index'])->name('settings.groups');
        Route::post('/settings/groups', [SettingsGroupsController::class, 'store'])->name('settings.groups.store');
        Route::put('/settings/groups/{group}', [SettingsGroupsController::class, 'update'])->name('settings.groups.update');
        Route::delete('/settings/groups/{group}', [SettingsGroupsController::class, 'destroy'])->name('settings.groups.destroy');

        // Vault lock policy (trusted-device days + public-computer idle timeout).
        Route::get('/settings/security', [SettingsSecurityController::class, 'edit'])->name('settings.security.edit');
        Route::put('/settings/security', [SettingsSecurityController::class, 'update'])->name('settings.security.update');

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
        Route::post('/settings/backup/runs/{run}/verify', [SettingsBackupController::class, 'verifyRun'])->middleware('throttle:10,1')->name('settings.backup.runs.verify');
        Route::post('/settings/backup/runs/{run}/cancel', [SettingsBackupController::class, 'cancelRun'])->name('settings.backup.runs.cancel');
    });

    // POST /logout is owned by Fortify (AuthenticatedSessionController@destroy).

    // Gallery: photos + albums are plaintext-relational now (pivot). The page
    // hydrates photos + albums + usage inline; all CRUD is the relational core
    // below. The legacy ZK routes (/gallery/store, /gallery/raw/{blob}, …) are
    // kept for now and never collide (relational uses /gallery/photos*,
    // /gallery/albums*, /gallery/data, /gallery/photos/chunk/*).
    Route::get('/gallery', [GalleryController::class, 'page'])->middleware('module:gallery')->name('gallery.index');

    // Plaintext-relational Gallery core (pivot): photos + albums as rows, bytes
    // (original + server renditions) plaintext on the file disk.
    Route::middleware('module:gallery')->group(function (): void {
        Route::get('/gallery/data', [GalleryController::class, 'data'])->name('gallery.rel.data');
        Route::get('/gallery/trash', [GalleryController::class, 'trashed'])->name('gallery.rel.trash');
        Route::post('/gallery/photos', [GalleryController::class, 'upload'])->middleware('throttle:1200,1')->name('gallery.rel.upload');
        Route::post('/gallery/photos/trash/empty', [GalleryController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('gallery.rel.empty');
        Route::put('/gallery/photos/{photo}', [GalleryController::class, 'update'])->whereNumber('photo')->middleware('throttle:600,1')->name('gallery.rel.update');
        Route::post('/gallery/photos/{photo}/toggle', [GalleryController::class, 'toggle'])->whereNumber('photo')->middleware('throttle:1200,1')->name('gallery.rel.toggle');
        Route::delete('/gallery/photos/{photo}', [GalleryController::class, 'destroy'])->whereNumber('photo')->middleware('throttle:600,1')->name('gallery.rel.destroy');
        Route::get('/gallery/photos/{photo}/raw', [GalleryController::class, 'raw'])->whereNumber('photo')->middleware('throttle:3000,1')->name('gallery.rel.raw');
        Route::get('/gallery/photos/{photo}/thumb', [GalleryController::class, 'thumb'])->whereNumber('photo')->middleware('throttle:6000,1')->name('gallery.rel.thumb');
        Route::get('/gallery/photos/{photo}/medium', [GalleryController::class, 'medium'])->whereNumber('photo')->middleware('throttle:6000,1')->name('gallery.rel.medium');
        Route::get('/gallery/photos/{photo}/motion', [GalleryController::class, 'motion'])->whereNumber('photo')->middleware('throttle:3000,1')->name('gallery.rel.motion');
        Route::post('/gallery/photos/{id}/restore', [GalleryController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('gallery.rel.restore');
        Route::delete('/gallery/photos/{id}/force', [GalleryController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('gallery.rel.force');

        Route::post('/gallery/photos/chunk/init', [GalleryController::class, 'chunkInit'])->middleware('throttle:600,1')->name('gallery.rel.chunk.init');
        Route::post('/gallery/photos/chunk/part', [GalleryController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('gallery.rel.chunk.part');
        Route::post('/gallery/photos/chunk/complete', [GalleryController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('gallery.rel.chunk.complete');
        Route::post('/gallery/photos/chunk/abort', [GalleryController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('gallery.rel.chunk.abort');

        Route::get('/gallery/albums', [GalleryController::class, 'albums'])->name('gallery.rel.albums');
        Route::post('/gallery/albums', [GalleryController::class, 'storeAlbum'])->middleware('throttle:600,1')->name('gallery.rel.albums.store');
        Route::put('/gallery/albums/{album}', [GalleryController::class, 'updateAlbum'])->whereNumber('album')->middleware('throttle:600,1')->name('gallery.rel.albums.update');
        Route::delete('/gallery/albums/{album}', [GalleryController::class, 'destroyAlbum'])->whereNumber('album')->middleware('throttle:600,1')->name('gallery.rel.albums.destroy');
        Route::post('/gallery/albums/{album}/photos', [GalleryController::class, 'addPhotos'])->whereNumber('album')->middleware('throttle:600,1')->name('gallery.rel.albums.photos.add');
        Route::delete('/gallery/albums/{album}/photos/{photo}', [GalleryController::class, 'removePhoto'])->whereNumber(['album', 'photo'])->middleware('throttle:600,1')->name('gallery.rel.albums.photos.remove');
        Route::post('/gallery/albums/{album}/cover', [GalleryController::class, 'setCover'])->whereNumber('album')->middleware('throttle:600,1')->name('gallery.rel.albums.cover');

        Route::post('/gallery/rel-shares', [GalleryController::class, 'storeShare'])->middleware('throttle:60,1')->name('gallery.rel.shares.store');
        Route::put('/gallery/rel-shares/{share}', [GalleryController::class, 'updateShare'])->whereNumber('share')->middleware('throttle:60,1')->name('gallery.rel.shares.update');
        Route::delete('/gallery/rel-shares/{share}', [GalleryController::class, 'destroyShare'])->whereNumber('share')->middleware('throttle:60,1')->name('gallery.rel.shares.destroy');

        // ML: CLIP semantic search + face/people recognition (pgvector-backed;
        // empty/degraded when ML is off or the vector extension is absent).
        Route::get('/gallery/search', [GalleryController::class, 'search'])->middleware('throttle:120,1')->name('gallery.rel.search');
        Route::get('/gallery/photos/{photo}/similar', [GalleryController::class, 'similar'])->whereNumber('photo')->middleware('throttle:120,1')->name('gallery.rel.similar');
        Route::post('/gallery/photos/{photo}/reprocess', [GalleryController::class, 'reprocess'])->whereNumber('photo')->middleware('throttle:120,1')->name('gallery.rel.reprocess');
        Route::get('/gallery/people', [GalleryController::class, 'people'])->name('gallery.rel.people');
        Route::get('/gallery/people/{person}', [GalleryController::class, 'person'])->whereNumber('person')->name('gallery.rel.people.show');
        Route::put('/gallery/people/{person}', [GalleryController::class, 'updatePerson'])->whereNumber('person')->middleware('throttle:600,1')->name('gallery.rel.people.update');
        Route::delete('/gallery/people/{person}', [GalleryController::class, 'destroyPerson'])->whereNumber('person')->middleware('throttle:600,1')->name('gallery.rel.people.destroy');
        Route::post('/gallery/people/merge', [GalleryController::class, 'mergePeople'])->middleware('throttle:600,1')->name('gallery.rel.people.merge');
        Route::post('/gallery/faces/{face}/assign', [GalleryController::class, 'assignFace'])->whereNumber('face')->middleware('throttle:600,1')->name('gallery.rel.faces.assign');
        Route::post('/gallery/faces/{face}/hide', [GalleryController::class, 'hideFace'])->whereNumber('face')->middleware('throttle:600,1')->name('gallery.rel.faces.hide');
        Route::get('/gallery/faces/{face}/crop', [GalleryController::class, 'faceCrop'])->whereNumber('face')->middleware('throttle:6000,1')->name('gallery.rel.faces.crop');
    });

    // Files: the whole directory tree + bytes are plaintext-relational now (pivot).
    // The page hydrates folders + files inline; all CRUD is the relational core below.
    Route::get('/files', [FilesController::class, 'page'])->middleware('module:files')->name('files.index');

    // Plaintext-relational Files core (pivot): personal files + folders as rows,
    // bytes plaintext on the file disk.
    Route::middleware('module:files')->group(function (): void {
        Route::get('/files/trash', [FilesController::class, 'trashed'])->name('files.rel.trash');
        Route::get('/files/entries', [FilesController::class, 'index'])->name('files.rel.index');
        Route::get('/files/search', [FileSearchController::class, 'search'])->middleware('throttle:120,1')->name('files.rel.search');
        Route::post('/files/entries', [FilesController::class, 'upload'])->middleware('throttle:1200,1')->name('files.rel.upload');
        Route::post('/files/entries/trash/empty', [FilesController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('files.rel.empty');
        Route::put('/files/entries/{file}', [FilesController::class, 'update'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.update');
        Route::delete('/files/entries/{file}', [FilesController::class, 'destroy'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.destroy');
        Route::get('/files/entries/{file}/raw', [FilesController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('files.rel.raw');
        Route::post('/files/entries/{file}/content', [FilesController::class, 'replaceContent'])->whereNumber('file')->middleware('throttle:1200,1')->name('files.rel.content');
        Route::post('/files/entries/{file}/toggle', [FilesController::class, 'toggle'])->whereNumber('file')->middleware('throttle:1200,1')->name('files.rel.toggle');
        Route::get('/files/entries/{file}/versions', [FilesController::class, 'versions'])->whereNumber('file')->name('files.rel.versions');
        Route::get('/files/entries/{file}/versions/{version}/raw', [FilesController::class, 'versionRaw'])->whereNumber(['file', 'version'])->middleware('throttle:3000,1')->name('files.rel.version.raw');
        Route::post('/files/entries/{file}/versions/{version}/restore', [FilesController::class, 'restoreVersion'])->whereNumber(['file', 'version'])->middleware('throttle:600,1')->name('files.rel.version.restore');
        Route::post('/files/entries/{id}/restore', [FilesController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('files.rel.restore');
        Route::delete('/files/entries/{id}/force', [FilesController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('files.rel.force');

        Route::get('/files/folders', [FilesController::class, 'folders'])->name('files.rel.folders');
        Route::post('/files/folders', [FilesController::class, 'storeFolder'])->middleware('throttle:600,1')->name('files.rel.folders.store');
        Route::put('/files/folders/{folder}', [FilesController::class, 'renameFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('files.rel.folders.update');
        Route::post('/files/folders/{folder}/move', [FilesController::class, 'moveFolder'])->whereNumber('folder')->middleware('throttle:1200,1')->name('files.rel.folders.move');
        Route::delete('/files/folders/{folder}', [FilesController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('files.rel.folders.destroy');

        Route::post('/files/upload/chunk/init', [FilesController::class, 'chunkInit'])->middleware('throttle:600,1')->name('files.rel.chunk.init');
        Route::post('/files/upload/chunk/part', [FilesController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('files.rel.chunk.part');
        Route::post('/files/upload/chunk/complete', [FilesController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('files.rel.chunk.complete');
        Route::post('/files/upload/chunk/abort', [FilesController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('files.rel.chunk.abort');

        // Public share links (owner side) — plaintext /file-share/{token}.
        Route::post('/files/rel-shares', [FilesController::class, 'storeShare'])->middleware('throttle:60,1')->name('files.rel.shares.store');
        Route::put('/files/rel-shares/{share}', [FilesController::class, 'updateShare'])->whereNumber('share')->middleware('throttle:60,1')->name('files.rel.shares.update');
        Route::delete('/files/rel-shares/{share}', [FilesController::class, 'destroyShare'])->whereNumber('share')->middleware('throttle:60,1')->name('files.rel.shares.destroy');

        // Cross-user plaintext folder sharing (pivot). Owner side: grant/list/manage
        // shares of one's own folders. Member side (/shared-with-me): browse the
        // shared subtree, download, and — as an editor — upload/rename/delete.
        Route::get('/files/folder-shares', [SharedFolderController::class, 'index'])->name('files.folder-shares.index');
        Route::post('/files/folder-shares', [SharedFolderController::class, 'store'])->middleware('throttle:60,1')->name('files.folder-shares.store');
        Route::put('/files/folder-shares/{share}/members', [SharedFolderController::class, 'updateMember'])->whereNumber('share')->middleware('throttle:60,1')->name('files.folder-shares.members.update');
        Route::delete('/files/folder-shares/{share}/members', [SharedFolderController::class, 'removeMember'])->whereNumber('share')->middleware('throttle:60,1')->name('files.folder-shares.members.remove');
        Route::delete('/files/folder-shares/{share}', [SharedFolderController::class, 'destroy'])->whereNumber('share')->middleware('throttle:60,1')->name('files.folder-shares.destroy');
        Route::get('/shared-with-me', [SharedWithMeController::class, 'index'])->name('shared-with-me.index');
        Route::get('/shared-with-me/{share}', [SharedWithMeController::class, 'browse'])->whereNumber('share')->name('shared-with-me.browse');
        Route::get('/shared-with-me/{share}/files/{file}/raw', [SharedWithMeController::class, 'raw'])->whereNumber(['share', 'file'])->middleware('throttle:3000,1')->name('shared-with-me.raw');
        Route::post('/shared-with-me/{share}/upload', [SharedWithMeController::class, 'upload'])->whereNumber('share')->middleware('throttle:1200,1')->name('shared-with-me.upload');
        Route::put('/shared-with-me/{share}/files/{file}', [SharedWithMeController::class, 'rename'])->whereNumber(['share', 'file'])->middleware('throttle:600,1')->name('shared-with-me.rename');
        Route::delete('/shared-with-me/{share}/files/{file}', [SharedWithMeController::class, 'destroy'])->whereNumber(['share', 'file'])->middleware('throttle:600,1')->name('shared-with-me.destroy');
    });

    // Plaintext-relational Notes (pivot Etappe 1). Server-rendered page + JSON CRUD + trash.
    Route::middleware('module:notes')->group(function (): void {
        Route::get('/notes/list', [NotesController::class, 'index'])->name('notes.list');
        Route::post('/notes', [NotesController::class, 'store'])->middleware('throttle:600,1')->name('notes.rel.store');
        Route::put('/notes/{note}', [NotesController::class, 'update'])->whereNumber('note')->middleware('throttle:600,1')->name('notes.rel.update');
        Route::delete('/notes/{note}', [NotesController::class, 'destroy'])->whereNumber('note')->middleware('throttle:600,1')->name('notes.rel.destroy');
        Route::get('/notes/trash', [NotesController::class, 'trashed'])->name('notes.trash');
        Route::post('/notes/{id}/restore', [NotesController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('notes.rel.restore');
        Route::delete('/notes/{id}/force', [NotesController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('notes.rel.force');
        Route::post('/notes/trash/empty', [NotesController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('notes.rel.empty');
    });

    // Gallery geocoding (kept): forward-geocode a place query for the bulk
    // location picker. Passes through the server only (client CSP forbids
    // third-party calls) and is never persisted.
    Route::get('/gallery/geocode', [GalleryProcessController::class, 'geocode'])->middleware('throttle:60,1')->name('gallery.geocode');

    // Notes live entirely in the zero-knowledge store now; only the page shell
    // remains here (all data flows through GET/PUT /store).
    Route::get('/notes', [NotesController::class, 'page'])->middleware('module:notes')->name('notes.index');
    // To-dos: zero-knowledge, living entirely in the opaque store manifest.
    // Plaintext-relational Todos (pivot Etappe 1).
    Route::middleware('module:todos')->group(function (): void {
        Route::get('/todos', [TodosController::class, 'page'])->name('todos.index');
        Route::get('/todos/list', [TodosController::class, 'index'])->name('todos.list');
        Route::get('/todos/trash', [TodosController::class, 'trashed'])->name('todos.trash');
        Route::post('/todos', [TodosController::class, 'store'])->middleware('throttle:600,1')->name('todos.store');
        Route::put('/todos/{todo}', [TodosController::class, 'update'])->whereNumber('todo')->middleware('throttle:600,1')->name('todos.update');
        Route::post('/todos/{todo}/toggle', [TodosController::class, 'toggle'])->whereNumber('todo')->middleware('throttle:1200,1')->name('todos.toggle');
        Route::delete('/todos/{todo}', [TodosController::class, 'destroy'])->whereNumber('todo')->middleware('throttle:600,1')->name('todos.destroy');
        Route::post('/todos/{id}/restore', [TodosController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('todos.restore');
        Route::delete('/todos/{id}/force', [TodosController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('todos.force');
        Route::post('/todos/trash/empty', [TodosController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('todos.empty');
        Route::get('/todo-lists', [TodosController::class, 'lists'])->name('todos.lists');
        Route::post('/todo-lists', [TodosController::class, 'storeList'])->middleware('throttle:600,1')->name('todos.lists.store');
        Route::put('/todo-lists/{list}', [TodosController::class, 'renameList'])->whereNumber('list')->middleware('throttle:600,1')->name('todos.lists.rename');
        Route::delete('/todo-lists/{list}', [TodosController::class, 'destroyList'])->whereNumber('list')->middleware('throttle:600,1')->name('todos.lists.destroy');
    });
    // Bookmarks: zero-knowledge, driven client-side from the opaque manifest.
    // Plaintext-relational Bookmarks (pivot Etappe 1).
    Route::middleware('module:bookmarks')->group(function (): void {
        Route::get('/bookmarks', [BookmarksController::class, 'page'])->name('bookmarks.index');
        Route::get('/bookmarks/list', [BookmarksController::class, 'index'])->name('bookmarks.list');
        Route::get('/bookmarks/trash', [BookmarksController::class, 'trashed'])->name('bookmarks.trash');
        Route::post('/bookmarks', [BookmarksController::class, 'store'])->middleware('throttle:600,1')->name('bookmarks.store');
        Route::put('/bookmarks/{bookmark}', [BookmarksController::class, 'update'])->whereNumber('bookmark')->middleware('throttle:600,1')->name('bookmarks.update');
        Route::post('/bookmarks/{bookmark}/toggle', [BookmarksController::class, 'toggle'])->whereNumber('bookmark')->middleware('throttle:1200,1')->name('bookmarks.toggle');
        Route::post('/bookmarks/{bookmark}/move', [BookmarksController::class, 'move'])->whereNumber('bookmark')->middleware('throttle:1200,1')->name('bookmarks.move');
        Route::delete('/bookmarks/{bookmark}', [BookmarksController::class, 'destroy'])->whereNumber('bookmark')->middleware('throttle:600,1')->name('bookmarks.destroy');
        Route::post('/bookmarks/{id}/restore', [BookmarksController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('bookmarks.restore');
        Route::delete('/bookmarks/{id}/force', [BookmarksController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('bookmarks.force');
        Route::post('/bookmarks/trash/empty', [BookmarksController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('bookmarks.empty');
        Route::get('/bookmark-folders', [BookmarksController::class, 'folders'])->name('bookmarks.folders');
        Route::post('/bookmark-folders', [BookmarksController::class, 'storeFolder'])->middleware('throttle:600,1')->name('bookmarks.folders.store');
        Route::put('/bookmark-folders/{folder}', [BookmarksController::class, 'updateFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('bookmarks.folders.update');
        Route::post('/bookmark-folders/{folder}/move', [BookmarksController::class, 'moveFolder'])->whereNumber('folder')->middleware('throttle:1200,1')->name('bookmarks.folders.move');
        Route::delete('/bookmark-folders/{folder}', [BookmarksController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('bookmarks.folders.destroy');
    });
    // Login/bank site-icon (BIMI/favicon) proxy: domain sent transiently, never
    // stored; SSRF-guarded. Retained for the Finance module (bank logos / partner
    // favicons); the password manager that first used it has been removed.
    Route::get('/passwords/icon', [PasswordIconController::class, 'fetch'])->middleware('throttle:1200,1')->name('passwords.icon');
    // Plaintext-relational Health (pivot). Server-rendered page + JSON per-record
    // CRUD (profile / entries / fasts). Sensitive columns are `encrypted`-cast.
    Route::get('/health', [HealthController::class, 'page'])->middleware('module:health')->name('health.index');
    Route::middleware('module:health')->group(function (): void {
        Route::get('/health/data', [HealthController::class, 'index'])->name('health.data');
        Route::put('/health/profile', [HealthController::class, 'saveProfile'])->middleware('throttle:600,1')->name('health.profile.save');
        Route::get('/health/entries', [HealthController::class, 'entries'])->name('health.entries');
        Route::post('/health/entries', [HealthController::class, 'storeEntry'])->middleware('throttle:600,1')->name('health.entries.store');
        Route::put('/health/entries/{entry}', [HealthController::class, 'updateEntry'])->whereNumber('entry')->middleware('throttle:600,1')->name('health.entries.update');
        Route::delete('/health/entries/{entry}', [HealthController::class, 'destroyEntry'])->whereNumber('entry')->middleware('throttle:600,1')->name('health.entries.destroy');
        Route::get('/health/fasts', [HealthController::class, 'fasts'])->name('health.fasts');
        Route::get('/health/fasts/active', [HealthController::class, 'activeFast'])->name('health.fasts.active');
        Route::post('/health/fasts', [HealthController::class, 'startFast'])->middleware('throttle:600,1')->name('health.fasts.start');
        Route::post('/health/fasts/{fast}/stop', [HealthController::class, 'stopFast'])->whereNumber('fast')->middleware('throttle:600,1')->name('health.fasts.stop');
        Route::put('/health/fasts/{fast}', [HealthController::class, 'updateFast'])->whereNumber('fast')->middleware('throttle:600,1')->name('health.fasts.update');
        Route::delete('/health/fasts/{fast}', [HealthController::class, 'destroyFast'])->whereNumber('fast')->middleware('throttle:600,1')->name('health.fasts.destroy');
    });
    // Plaintext-relational Finance (pivot): invoices + partners + payment methods
    // + bank transactions + projects + categories as owner-scoped rows. The
    // per-user company profile (printed on invoices) stays in the user's settings.
    Route::middleware('module:finance')->group(function (): void {
        Route::get('/finance', [FinanceController::class, 'page'])->name('finance.index');
        Route::get('/finance/data', [FinanceController::class, 'index'])->name('finance.data');
        Route::get('/finance/trash', [FinanceController::class, 'trash'])->name('finance.trash');

        // Partners
        Route::post('/finance/partners', [FinanceController::class, 'storePartner'])->middleware('throttle:600,1')->name('finance.partners.store');
        Route::put('/finance/partners/{partner}', [FinanceController::class, 'updatePartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('finance.partners.update');
        Route::delete('/finance/partners/{partner}', [FinanceController::class, 'destroyPartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('finance.partners.destroy');
        Route::post('/finance/partners/{id}/restore', [FinanceController::class, 'restorePartner'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.partners.restore');
        Route::delete('/finance/partners/{id}/force', [FinanceController::class, 'forceDeletePartner'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.partners.force');

        // Payment methods
        Route::post('/finance/payment-methods', [FinanceController::class, 'storePaymentMethod'])->middleware('throttle:600,1')->name('finance.payment-methods.store');
        Route::put('/finance/payment-methods/{paymentMethod}', [FinanceController::class, 'updatePaymentMethod'])->whereNumber('paymentMethod')->middleware('throttle:600,1')->name('finance.payment-methods.update');
        Route::delete('/finance/payment-methods/{paymentMethod}', [FinanceController::class, 'destroyPaymentMethod'])->whereNumber('paymentMethod')->middleware('throttle:600,1')->name('finance.payment-methods.destroy');
        Route::post('/finance/payment-methods/{id}/restore', [FinanceController::class, 'restorePaymentMethod'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.payment-methods.restore');
        Route::delete('/finance/payment-methods/{id}/force', [FinanceController::class, 'forceDeletePaymentMethod'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.payment-methods.force');

        // Projects
        Route::post('/finance/projects', [FinanceController::class, 'storeProject'])->middleware('throttle:600,1')->name('finance.projects.store');
        Route::put('/finance/projects/{project}', [FinanceController::class, 'updateProject'])->whereNumber('project')->middleware('throttle:600,1')->name('finance.projects.update');
        Route::post('/finance/projects/{project}/move', [FinanceController::class, 'moveProject'])->whereNumber('project')->middleware('throttle:1200,1')->name('finance.projects.move');
        Route::delete('/finance/projects/{project}', [FinanceController::class, 'destroyProject'])->whereNumber('project')->middleware('throttle:600,1')->name('finance.projects.destroy');
        Route::post('/finance/projects/{id}/restore', [FinanceController::class, 'restoreProject'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.projects.restore');
        Route::delete('/finance/projects/{id}/force', [FinanceController::class, 'forceDeleteProject'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.projects.force');

        // Categories (hard-deleted lookup list)
        Route::post('/finance/categories', [FinanceController::class, 'storeCategory'])->middleware('throttle:600,1')->name('finance.categories.store');
        Route::put('/finance/categories/{category}', [FinanceController::class, 'updateCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('finance.categories.update');
        Route::delete('/finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('finance.categories.destroy');

        // Invoices
        Route::post('/finance/invoices', [FinanceController::class, 'storeInvoice'])->middleware('throttle:600,1')->name('finance.invoices.store');
        Route::put('/finance/invoices/{invoice}', [FinanceController::class, 'updateInvoice'])->whereNumber('invoice')->middleware('throttle:600,1')->name('finance.invoices.update');
        Route::post('/finance/invoices/{invoice}/finalize', [FinanceController::class, 'finalizeInvoice'])->whereNumber('invoice')->middleware('throttle:600,1')->name('finance.invoices.finalize');
        Route::delete('/finance/invoices/{invoice}', [FinanceController::class, 'destroyInvoice'])->whereNumber('invoice')->middleware('throttle:600,1')->name('finance.invoices.destroy');
        Route::post('/finance/invoices/{id}/restore', [FinanceController::class, 'restoreInvoice'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.invoices.restore');
        Route::delete('/finance/invoices/{id}/force', [FinanceController::class, 'forceDeleteInvoice'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.invoices.force');
        Route::post('/finance/invoices/{invoice}/pdf', [FinanceController::class, 'uploadInvoicePdf'])->whereNumber('invoice')->middleware('throttle:1200,1')->name('finance.invoices.pdf.upload');
        Route::get('/finance/invoices/{invoice}/pdf', [FinanceController::class, 'invoicePdf'])->whereNumber('invoice')->middleware('throttle:3000,1')->name('finance.invoices.pdf');

        // Bank transactions
        Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction'])->middleware('throttle:600,1')->name('finance.transactions.store');
        Route::post('/finance/transactions/bulk', [FinanceController::class, 'bulkTransactions'])->middleware('throttle:120,1')->name('finance.transactions.bulk');
        Route::put('/finance/transactions/{transaction}', [FinanceController::class, 'updateTransaction'])->whereNumber('transaction')->middleware('throttle:600,1')->name('finance.transactions.update');
        Route::delete('/finance/transactions/{transaction}', [FinanceController::class, 'destroyTransaction'])->whereNumber('transaction')->middleware('throttle:600,1')->name('finance.transactions.destroy');
        Route::post('/finance/transactions/{id}/restore', [FinanceController::class, 'restoreTransaction'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.transactions.restore');
        Route::delete('/finance/transactions/{id}/force', [FinanceController::class, 'forceDeleteTransaction'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.transactions.force');
        Route::post('/finance/transactions/{transaction}/receipts', [FinanceController::class, 'attachReceipt'])->whereNumber('transaction')->middleware('throttle:1200,1')->name('finance.transactions.receipts.store');
        Route::get('/finance/transactions/{transaction}/receipts/{receipt}/raw', [FinanceController::class, 'receiptRaw'])->whereNumber('transaction')->middleware('throttle:3000,1')->name('finance.transactions.receipts.raw');
        Route::delete('/finance/transactions/{transaction}/receipts/{receipt}', [FinanceController::class, 'destroyReceipt'])->whereNumber('transaction')->middleware('throttle:600,1')->name('finance.transactions.receipts.destroy');
    });
    Route::redirect('/invoices', '/finance'); // old bookmarks
    Route::get('/settings/company', [SettingsCompanyController::class, 'edit'])->name('settings.company.edit');
    Route::put('/settings/company', [SettingsCompanyController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/company/logo', [SettingsCompanyController::class, 'logo'])->name('settings.company.logo');
    // Explore (map/GPS): the records (tracks, couplings, tolerances) live in the
    // opaque `explore` module store (GET/PUT /store/explore); only the optional
    // raw track files are opaque content blobs (explore/{blob}). Same
    // controller-reuse, owner-scoped, zero-knowledge as the contacts blobs.
    Route::get('/explore', [ExploreController::class, 'page'])->middleware('module:explore')->name('explore');
    // Plaintext-relational Explore (pivot): tracks + photo couplings + tolerances.
    // Track point lists are location PII → `encrypted`-cast. Same controller as api.
    Route::middleware('module:explore')->group(function (): void {
        Route::get('/explore/data', [ExploreController::class, 'index'])->name('explore.data');
        Route::get('/explore/trash', [ExploreController::class, 'trash'])->name('explore.trash');
        Route::post('/explore/tracks', [ExploreController::class, 'storeTrack'])->middleware('throttle:600,1')->name('explore.tracks.store');
        Route::put('/explore/tracks/{track}', [ExploreController::class, 'updateTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('explore.tracks.update');
        Route::delete('/explore/tracks/{track}', [ExploreController::class, 'destroyTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('explore.tracks.destroy');
        Route::post('/explore/tracks/{track}/restore', [ExploreController::class, 'restoreTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('explore.tracks.restore');
        Route::delete('/explore/tracks/{track}/force', [ExploreController::class, 'forceDeleteTrack'])->whereNumber('track')->middleware('throttle:600,1')->name('explore.tracks.force');
        Route::post('/explore/tracks/{track}/file', [ExploreController::class, 'uploadTrackFile'])->whereNumber('track')->middleware('throttle:600,1')->name('explore.tracks.file.upload');
        Route::get('/explore/tracks/{track}/file', [ExploreController::class, 'trackFile'])->whereNumber('track')->middleware('throttle:600,1')->name('explore.tracks.file');
        Route::post('/explore/couplings', [ExploreController::class, 'setCoupling'])->middleware('throttle:600,1')->name('explore.couplings.set');
        Route::delete('/explore/couplings', [ExploreController::class, 'deleteCoupling'])->middleware('throttle:600,1')->name('explore.couplings.destroy');
        Route::put('/explore/settings', [ExploreController::class, 'saveSettings'])->middleware('throttle:600,1')->name('explore.settings.save');
    });
    // Legacy ZK explore blob endpoints (frontend switch + teardown is a later step).
    // Explore tour-planner auto-routing: snap clicked waypoints to real paths via
    // an OSRM-compatible upstream (SSRF-guarded, coordinates never logged/persisted,
    // clean {geometry:null} when the upstream is unset/unreachable). User-initiated,
    // opt-in egress — same class as the gallery place-picker geocoding.
    Route::get('/maps/route', [MapController::class, 'route'])->middleware('throttle:180,1')->name('maps.route');
    // Resolve a Google-Maps short link (maps.app.goo.gl/…) to coordinates for the
    // Explore search. Google-hosts-only egress, link never logged; same opt-in class.
    Route::get('/maps/resolve', [MapController::class, 'resolve'])->middleware('throttle:30,1')->name('maps.resolve');
    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (shared by mail attachments and the file browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->name('paperless.documents');
});
