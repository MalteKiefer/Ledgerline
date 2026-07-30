<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BookmarksController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\ExploreBlobController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\GalleryBlobController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GalleryProcessController;
use App\Http\Controllers\GalleryShareController;
use App\Http\Controllers\GalleryStoreController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\InvoiceBlobController;
use App\Http\Controllers\InvoicesStoreController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\ModuleStoreController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicShareController;
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
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\TodosController;
use App\Http\Controllers\VaultController;
use Illuminate\Support\Facades\Route;

// The root simply forwards to the dashboard; unauthenticated visitors are then
// redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('dashboard'));

// Prometheus metrics for external scraping — no session; guarded by its own
// token (OPS_METRICS_TOKEN) and disabled when unset. Rate-limited.
Route::get('/metrics', [MetricsController::class, 'index'])->middleware('throttle:60,1')->name('metrics');

// Public, unauthenticated gallery-album share links. Zero-knowledge: the server
// only serves the sealed manifest + opaque ciphertext blobs on the owner's
// allow-list; the decryption key rides in the URL fragment and never arrives
// here. The optional password gate is hard-throttled; blob/manifest reads are
// generous (a shared album loads many thumbnails).
Route::prefix('s/{token}')->name('public.share.')->group(function (): void {
    Route::get('/', [PublicShareController::class, 'show'])->middleware('throttle:120,1')->name('show');
    Route::get('/meta', [PublicShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
    Route::post('/unlock', [PublicShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
    Route::get('/manifest', [PublicShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
    Route::get('/blob/{ref}', [PublicShareController::class, 'blob'])->middleware('throttle:3000,1')->name('blob');
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
    Route::get('/profile/encryption', [ProfileController::class, 'encryption'])->name('profile.encryption');
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

    // Zero-knowledge gallery: the client holds all keys and renders entirely
    // from the sealed index + decrypted blobs. The server ships only the shell
    // here; upload/process/blob/store live in the dedicated routes below.
    Route::get('/gallery', [GalleryController::class, 'index'])->middleware('module:gallery')->name('gallery.index');

    // Zero-knowledge encryption vault (Files): the server only stores ciphertext
    // and KDF params — never the passphrase, recovery code or vault key.
    Route::get('/vault', [VaultController::class, 'show'])->name('vault.show');
    Route::post('/vault', [VaultController::class, 'store'])->middleware('throttle:10,1')->name('vault.store');
    Route::put('/vault', [VaultController::class, 'rotate'])->middleware('throttle:10,1')->name('vault.rotate');

    // Files: the whole directory tree + bytes are plaintext-relational now (pivot).
    // The page hydrates folders + files inline; all CRUD is the relational core below.
    Route::get('/files', [FilesController::class, 'page'])->middleware('module:files')->name('files.index');

    // Plaintext-relational Files core (pivot): personal files + folders as rows,
    // bytes plaintext on the file disk. Distinct URIs (/files/entries, /files/folders,
    // /files/upload/chunk/*) so nothing collides with the ZK routes above.
    Route::middleware('module:files')->group(function (): void {
        Route::get('/files/trash', [FilesController::class, 'trashed'])->name('files.rel.trash');
        Route::get('/files/entries', [FilesController::class, 'index'])->name('files.rel.index');
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

    Route::get('/invoices/store', [InvoicesStoreController::class, 'show'])->middleware('module:finance')->name('invoices.store.show');
    Route::put('/invoices/store', [InvoicesStoreController::class, 'save'])->middleware(['throttle:600,1', 'module:finance'])->name('invoices.store.save');
    Route::post('/invoices/upload', [InvoiceBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('invoices.upload');
    Route::get('/invoices/raw/{blob}', [InvoiceBlobController::class, 'raw'])->middleware('throttle:3000,1')->name('invoices.raw');
    Route::post('/invoices/raw-batch', [InvoiceBlobController::class, 'rawBatch'])->middleware('throttle:3000,1')->name('invoices.raw-batch');
    Route::post('/invoices/blobs/reconcile', [InvoiceBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('invoices.blobs.reconcile');

    // Per-module sealed stores (Store v3 split): one opaque row per module.
    Route::get('/store/{module}', [ModuleStoreController::class, 'show'])->whereAlpha('module')->middleware('module')->name('module-store.show');
    Route::put('/store/{module}', [ModuleStoreController::class, 'save'])->whereAlpha('module')->middleware('throttle:1200,1')->middleware('module')->name('module-store.save');

    // Opaque zero-knowledge gallery index (photo/album/people structure sealed).
    Route::get('/gallery/store', [GalleryStoreController::class, 'show'])->middleware('module:gallery')->name('gallery.store.show');
    Route::put('/gallery/store', [GalleryStoreController::class, 'save'])->middleware(['throttle:600,1', 'module:gallery'])->name('gallery.store.save');
    // Public share links for an album: the client seals the share manifest (photo
    // list + per-blob keys re-wrapped under the link's fragment key) before it
    // arrives, so these only ever carry ciphertext + coarse access controls.
    Route::post('/gallery/shares', [GalleryShareController::class, 'store'])->middleware('throttle:60,1')->name('gallery.shares.store');
    Route::put('/gallery/shares/{token}', [GalleryShareController::class, 'update'])->middleware('throttle:60,1')->name('gallery.shares.update');
    Route::delete('/gallery/shares/{token}', [GalleryShareController::class, 'destroy'])->middleware('throttle:60,1')->name('gallery.shares.destroy');
    // Zero-knowledge transform: the browser POSTs one photo's PLAINTEXT, we return
    // its derived data (renditions/exif/embedding/faces/place) and discard the
    // bytes — nothing is persisted server-side. embed-text embeds a search query.
    Route::post('/gallery/process', [GalleryProcessController::class, 'process'])->middleware('throttle:600,1')->name('gallery.process');
    Route::post('/gallery/analyze', [GalleryProcessController::class, 'analyze'])->middleware('throttle:600,1')->name('gallery.analyze');
    Route::post('/gallery/embed-text', [GalleryProcessController::class, 'embedText'])->middleware('throttle:300,1')->name('gallery.embed-text');
    Route::get('/gallery/geocode', [GalleryProcessController::class, 'geocode'])->middleware('throttle:60,1')->name('gallery.geocode');

    // Opaque zero-knowledge gallery content blobs (ciphertext bytes only).
    Route::get('/gallery/usage', [GalleryBlobController::class, 'usage'])->name('gallery.usage');
    Route::post('/gallery/blobs/reconcile', [GalleryBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('gallery.blobs.reconcile');
    Route::post('/gallery/upload', [GalleryBlobController::class, 'upload'])->middleware('throttle:1200,1')->name('gallery.upload');
    Route::post('/gallery/upload/init', [GalleryBlobController::class, 'chunkInit'])->middleware('throttle:600,1')->name('gallery.upload.init');
    Route::post('/gallery/upload/part', [GalleryBlobController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('gallery.upload.part');
    Route::post('/gallery/upload/complete', [GalleryBlobController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('gallery.upload.complete');
    Route::post('/gallery/upload/abort', [GalleryBlobController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('gallery.upload.abort');
    Route::get('/gallery/raw/{blob}', [GalleryBlobController::class, 'raw'])->middleware('throttle:3000,1')->name('gallery.raw');
    Route::post('/gallery/raw-batch', [GalleryBlobController::class, 'rawBatch'])->middleware('throttle:3000,1')->name('gallery.raw-batch');
    // Generous limit: emptying a large trash frees hundreds of blobs at once, and
    // each delete is owner-scoped, idempotent and cheap (unlink + ledger row).
    Route::delete('/gallery/blob/{blob}', [GalleryBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('gallery.blob.destroy');

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
    // Invoices: zero-knowledge, records in the opaque /store manifest. The per-user
    // company profile (printed on invoices) is plaintext in the user's settings.
    Route::view('/finance', 'invoices.index')->middleware('module:finance')->name('finance.index');
    Route::redirect('/invoices', '/finance'); // old bookmarks
    Route::get('/settings/company', [SettingsCompanyController::class, 'edit'])->name('settings.company.edit');
    Route::put('/settings/company', [SettingsCompanyController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/company/logo', [SettingsCompanyController::class, 'logo'])->name('settings.company.logo');
    // Explore (map/GPS): the records (tracks, couplings, tolerances) live in the
    // opaque `explore` module store (GET/PUT /store/explore); only the optional
    // raw track files are opaque content blobs (explore/{blob}). Same
    // controller-reuse, owner-scoped, zero-knowledge as the contacts blobs.
    Route::get('/explore', ExploreController::class)->middleware('module:explore')->name('explore');
    Route::get('/explore/usage', [ExploreBlobController::class, 'usage'])->name('explore.usage');
    Route::post('/explore/blobs/reconcile', [ExploreBlobController::class, 'reconcile'])->middleware('throttle:120,1')->name('explore.blobs.reconcile');
    Route::post('/explore/upload', [ExploreBlobController::class, 'upload'])->middleware('throttle:600,1')->name('explore.upload');
    Route::get('/explore/raw/{blob}', [ExploreBlobController::class, 'raw'])->middleware('throttle:600,1')->name('explore.raw');
    Route::delete('/explore/blob/{blob}', [ExploreBlobController::class, 'deleteBlob'])->middleware('throttle:3000,1')->name('explore.blob.destroy');
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
