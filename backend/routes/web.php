<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressBookController;
use App\Http\Controllers\Api\MailAccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CalendarBookController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarShareController;
use App\Http\Controllers\CalendarTodoController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactDuplicateController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\ContactShareController;
use App\Http\Controllers\ContactSyncSourceController;
use App\Http\Controllers\CryptoController;
use App\Http\Controllers\DeadlineController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\GalleryCommentController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\GalleryPeopleController;
use App\Http\Controllers\GalleryShareController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\KeyServerController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MailAttachmentController;
use App\Http\Controllers\MailBlobController;
use App\Http\Controllers\MailDeleteOriginController;
use App\Http\Controllers\MailExportController;
use App\Http\Controllers\MailFlagController;
use App\Http\Controllers\MailFolderAdminController;
use App\Http\Controllers\MailFolderController;
use App\Http\Controllers\MailKeyController;
use App\Http\Controllers\MailLabelController;
use App\Http\Controllers\MailLogController;
use App\Http\Controllers\MailMessageController;
use App\Http\Controllers\MailMoveController;
use App\Http\Controllers\MailPushbackController;
use App\Http\Controllers\MailRuleController;
use App\Http\Controllers\MailSavedSearchController;
use App\Http\Controllers\MailSeenController;
use App\Http\Controllers\MailSendController;
use App\Http\Controllers\MailStatsController;
use App\Http\Controllers\MailTrashController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\MountController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\PublicFileShareController;
use App\Http\Controllers\PublicGalleryShareController;
use App\Http\Controllers\PublicGalleryUploadController;
use App\Http\Controllers\ReindexController;
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\CalendarController as SettingsCalendarController;
use App\Http\Controllers\Settings\CompanyController as SettingsCompanyController;
use App\Http\Controllers\Settings\ContactsController as SettingsContactsController;
use App\Http\Controllers\Settings\FilesController as SettingsFilesController;
use App\Http\Controllers\Settings\GroupsController as SettingsGroupsController;
use App\Http\Controllers\Settings\NotificationsController as SettingsNotificationsController;
use App\Http\Controllers\Settings\PaperlessController as SettingsPaperlessController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\Settings\SystemController;
use App\Http\Controllers\Settings\UsersController as SettingsUsersController;
use App\Http\Controllers\SharedFolderController;
use App\Http\Controllers\SharedGalleryController;
use App\Http\Controllers\SharedWithMeController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\WebDavAccessController;
use App\Http\Controllers\WebDavController;
use Illuminate\Support\Facades\Route;

// SPA-only: the Vue SPA is the sole UI and owns all client-side routing. The
// root (and every other UI path, via the catch-all at the bottom) serves the
// SPA shell; the SPA's router guard redirects to /login when /api/v1/me is 401.
Route::get('/', static fn () => view('spa'))->name('home');

// Prometheus metrics for external scraping — no session; guarded by its own
// token (OPS_METRICS_TOKEN) and disabled when unset. Rate-limited.
Route::get('/metrics', [MetricsController::class, 'index'])->middleware('throttle:60,1')->name('metrics');

// First-party auth (login, registration, password reset, email verification,
// two-factor) is owned by Laravel Fortify — see FortifyServiceProvider.

// Mail-independent invite / password-reset links: public consumption. The token
// is a hashed, single-use, expiring secret in the URL; the route is throttled and
// verifies it in constant time. Consuming it sets the user's password.
Route::get('/invite/{invite}/{token}', [InviteLinkController::class, 'show'])->middleware('throttle:invite')->name('invite.show');
Route::post('/invite/{invite}/{token}', [InviteLinkController::class, 'store'])->middleware('throttle:invite')->name('invite.store');

// Public, unauthenticated plaintext file-share links (optional password gate).
Route::prefix('file-share/{token}')->name('public.file-share.')->group(function (): void {
    Route::get('/', [PublicFileShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
    Route::post('/unlock', [PublicFileShareController::class, 'unlock'])->middleware('throttle:share-unlock')->name('unlock');
    Route::get('/manifest', [PublicFileShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
    Route::get('/file/{file}/raw', [PublicFileShareController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('file.raw');
});

// Public, unauthenticated subscribeable birthday .ics feed (token = capability).
Route::get('/contacts/birthdays/{token}.ics', [ContactShareController::class, 'ics'])->middleware('throttle:120,1')->name('public.contacts.birthdays');

// Public, unauthenticated gallery album share links (optional password gate).
Route::prefix('gallery-share/{token}')->name('public.gallery-share.')->group(function (): void {
    Route::get('/', [PublicGalleryShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
    Route::post('/unlock', [PublicGalleryShareController::class, 'unlock'])->middleware('throttle:share-unlock')->name('unlock');
    Route::get('/manifest', [PublicGalleryShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
    Route::get('/photo/{photo}/thumb', [PublicGalleryShareController::class, 'thumb'])->whereNumber('photo')->middleware('throttle:6000,1')->name('photo.thumb');
    Route::get('/photo/{photo}/preview', [PublicGalleryShareController::class, 'preview'])->whereNumber('photo')->middleware('throttle:6000,1')->name('photo.preview');
    Route::get('/photo/{photo}/raw', [PublicGalleryShareController::class, 'raw'])->whereNumber('photo')->middleware('throttle:3000,1')->name('photo.raw');
});

// Public, unauthenticated gallery album upload links (guest contributions).
Route::prefix('gallery-upload/{token}')->name('public.gallery-upload.')->group(function (): void {
    Route::get('/', [PublicGalleryUploadController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
    Route::post('/', [PublicGalleryUploadController::class, 'store'])->middleware('throttle:30,1')->name('store');
});

// WebDAV endpoint — Sabre handles auth (HTTP Basic) + the DAV protocol itself.
// Registered for every WebDAV verb (PROPFIND/MKCOL/MOVE/… are not covered by
// Route::any). CSRF is excluded for dav/* in bootstrap/app.php.
Route::match(
    ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD', 'PROPFIND', 'PROPPATCH', 'MKCOL', 'MOVE', 'COPY', 'LOCK', 'UNLOCK', 'REPORT'],
    '/dav/{path?}',
    WebDavController::class
)->where('path', '.*')->middleware('throttle:dav')->name('dav');

// CalDAV/CardDAV service discovery. In production a host-layer (Caddy) redirect
// serves these from the base domain; this self-contained fallback covers a plain
// deployment without one. Either way the full /dav/ URL works directly.
Route::get('/.well-known/caldav', static fn () => redirect('/dav/', 301))->name('well-known.caldav');
Route::get('/.well-known/carddav', static fn () => redirect('/dav/', 301))->name('well-known.carddav');

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
    Route::post('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    Route::get('/crypto/keys', [MailKeyController::class, 'index'])->name('crypto.keys.index');
    Route::post('/crypto/keys', [MailKeyController::class, 'store'])->middleware('throttle:60,1')->name('crypto.keys.store');
    Route::post('/crypto/keys/generate', [MailKeyController::class, 'generate'])->middleware('throttle:30,1')->name('crypto.keys.generate');
    Route::delete('/crypto/keys/{key}', [MailKeyController::class, 'destroy'])->whereNumber('key')->middleware('throttle:60,1')->name('crypto.keys.destroy');
    Route::post('/crypto/keys/{key}/export', [MailKeyController::class, 'export'])->whereNumber('key')->middleware('throttle:60,1')->name('crypto.keys.export');
    Route::get('/crypto/keyring', [CryptoController::class, 'keyring'])->name('crypto.keyring');
    Route::post('/crypto/recipients', [CryptoController::class, 'storeRecipient'])->middleware('throttle:60,1')->name('crypto.recipients.store');
    Route::delete('/crypto/recipients/{recipient}', [CryptoController::class, 'destroyRecipient'])->whereNumber('recipient')->middleware('throttle:60,1')->name('crypto.recipients.destroy');
    Route::get('/crypto/key-servers', [KeyServerController::class, 'index'])->name('crypto.key-servers.index');
    Route::post('/crypto/key-servers', [KeyServerController::class, 'store'])->middleware('throttle:30,1')->name('crypto.key-servers.store');
    Route::put('/crypto/key-servers/{keyServer}', [KeyServerController::class, 'update'])->whereNumber('keyServer')->middleware('throttle:30,1')->name('crypto.key-servers.update');
    Route::delete('/crypto/key-servers/{keyServer}', [KeyServerController::class, 'destroy'])->whereNumber('keyServer')->middleware('throttle:30,1')->name('crypto.key-servers.destroy');
    Route::post('/crypto/key-servers/search', [KeyServerController::class, 'search'])->middleware('throttle:30,1')->name('crypto.key-servers.search');
    Route::post('/crypto/key-servers/{keyServer}/import', [KeyServerController::class, 'import'])->whereNumber('keyServer')->middleware('throttle:30,1')->name('crypto.key-servers.import');
    Route::post('/crypto/recipients/{recipient}/refresh', [KeyServerController::class, 'refreshRecipient'])->whereNumber('recipient')->middleware('throttle:30,1')->name('crypto.recipients.refresh');
    Route::post('/crypto/keys/{key}/publish', [KeyServerController::class, 'publish'])->whereNumber('key')->middleware('throttle:30,1')->name('crypto.keys.publish');
    Route::post('/crypto/keys/{key}/check-presence', [KeyServerController::class, 'checkPresence'])->whereNumber('key')->middleware('throttle:30,1')->name('crypto.keys.check-presence');
    // WebDAV app-specific password (mount Files as a network drive).
    Route::put('/profile/webdav', [WebDavAccessController::class, 'update'])->middleware('throttle:20,1')->name('profile.webdav.update');
    Route::delete('/profile/webdav', [WebDavAccessController::class, 'destroy'])->middleware('throttle:20,1')->name('profile.webdav.destroy');
    // Profile page renders are served by the SPA (see the catch-all). Only the
    // data/artifact endpoints remain here.
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->middleware('throttle:30,1')->name('profile.avatar.store');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->middleware('throttle:30,1')->name('profile.avatar.destroy');
    Route::get('/search', [GlobalSearchController::class, 'search'])->middleware('throttle:120,1')->name('search');
    Route::post('/me/reindex', [ReindexController::class, 'me'])->middleware('throttle:6,1')->name('me.reindex');
    // Self-service account: GDPR export, session revocation, account erasure.
    Route::get('/account/export', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('account.export');
    Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->middleware('throttle:20,1')->name('account.sessions.revoke');
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
    Route::delete('/devices/{token}/push', [DevicePairingController::class, 'revokeDevicePush'])->middleware('throttle:20,1')->name('devices.push.revoke');

    // Local in-app notifications (bell menu).
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Settings page renders are served by the SPA (see the catch-all). Only the
    // data/mutation endpoints remain in this group.

    // Paperless-ngx: per-user integration (each user's own instance URL + token).
    Route::put('/settings/paperless', [SettingsPaperlessController::class, 'update'])->name('settings.paperless.update');
    Route::post('/settings/paperless/test', [SettingsPaperlessController::class, 'test'])->middleware('throttle:20,1')->name('settings.paperless.test');
    Route::post('/settings/paperless/sync', [SettingsPaperlessController::class, 'sync'])->middleware('throttle:20,1')->name('settings.paperless.sync');

    // Non-personal, workspace-wide settings — restricted to users with the admin
    // role (see User::managesGlobalSettings / the manage-global-settings gate).
    // Rate-limit the privileged web settings mutations (matches the /api/v1
    // admin twins) — internet-facing, so every admin write is capped.
    Route::middleware(['can:manage-global-settings', 'throttle:60,1'])->group(function (): void {
        // Admin settings page renders are served by the SPA (see the catch-all);
        // the security-log CSV/JSON export lives on /api/v1/security-log/export.
        Route::post('/settings/system/errors/{error}/resolve', [SystemController::class, 'resolveError'])->name('settings.system.errors.resolve');

        // User management: create, edit role + per-user limits, reset, delete.
        Route::post('/settings/users', [SettingsUsersController::class, 'store'])->name('settings.users.store');
        Route::post('/admin/reindex', [ReindexController::class, 'all'])->middleware('throttle:3,1')->name('admin.reindex');
        Route::put('/settings/users/{user}', [SettingsUsersController::class, 'update'])->name('settings.users.update');
        Route::post('/settings/users/{user}/reset-password', [SettingsUsersController::class, 'resetPassword'])->middleware('throttle:10,1')->name('settings.users.reset');
        Route::post('/settings/users/{user}/reset-2fa', [SettingsUsersController::class, 'resetTwoFactor'])->name('settings.users.reset2fa');
        Route::post('/settings/users/{user}/invite-link', [InviteLinkController::class, 'create'])->middleware('throttle:20,1')->name('settings.users.invite');
        Route::get('/settings/users/{user}/avatar', [SettingsUsersController::class, 'avatar'])->name('settings.users.avatar');
        Route::delete('/settings/users/{user}', [SettingsUsersController::class, 'destroy'])->name('settings.users.destroy');
        Route::post('/settings/registration', [SettingsUsersController::class, 'registration'])->name('settings.registration');

        // Group management: reusable limit templates + shareable flag.
        Route::post('/settings/groups', [SettingsGroupsController::class, 'store'])->name('settings.groups.store');
        Route::put('/settings/groups/{group}', [SettingsGroupsController::class, 'update'])->name('settings.groups.update');
        Route::delete('/settings/groups/{group}', [SettingsGroupsController::class, 'destroy'])->name('settings.groups.destroy');

        // Workspace security policy (per-user paired-device cap).
        Route::put('/settings/security', [SettingsSecurityController::class, 'update'])->name('settings.security.update');

        // Notification channels (mail / NTFY / webhook).
        Route::put('/settings/notifications', [SettingsNotificationsController::class, 'update'])->name('settings.notifications.update');
        Route::post('/settings/notifications/test', [SettingsNotificationsController::class, 'test'])->middleware('throttle:20,1')->name('settings.notifications.test');

        // Backup destinations, jobs and run history.
        Route::post('/settings/backup/destinations', [SettingsBackupController::class, 'storeDestination'])->name('settings.backup.destinations.store');
        Route::match(['post', 'put'], '/settings/backup/destinations/test', [SettingsBackupController::class, 'testDestination'])->middleware('throttle:20,1')->name('settings.backup.destinations.test');
        Route::put('/settings/backup/destinations/{destination}', [SettingsBackupController::class, 'updateDestination'])->name('settings.backup.destinations.update');
        Route::delete('/settings/backup/destinations/{destination}', [SettingsBackupController::class, 'destroyDestination'])->name('settings.backup.destinations.destroy');
        Route::post('/settings/backup/jobs', [SettingsBackupController::class, 'storeJob'])->name('settings.backup.jobs.store');
        Route::put('/settings/backup/jobs/{job}', [SettingsBackupController::class, 'updateJob'])->name('settings.backup.jobs.update');
        Route::delete('/settings/backup/jobs/{job}', [SettingsBackupController::class, 'destroyJob'])->name('settings.backup.jobs.destroy');
        Route::post('/settings/backup/jobs/{job}/run', [SettingsBackupController::class, 'runNow'])->middleware('throttle:10,1')->name('settings.backup.jobs.run');
        Route::get('/settings/backup/runs', [SettingsBackupController::class, 'runs'])->name('settings.backup.runs');
        Route::get('/settings/backup/runs/{run}/download', [SettingsBackupController::class, 'downloadRun'])->name('settings.backup.runs.download');
        Route::post('/settings/backup/runs/{run}/decrypt', [SettingsBackupController::class, 'decryptRun'])->middleware('throttle:10,1')->name('settings.backup.runs.decrypt');
        Route::post('/settings/backup/runs/{run}/verify', [SettingsBackupController::class, 'verifyRun'])->middleware('throttle:10,1')->name('settings.backup.runs.verify');
        Route::post('/settings/backup/runs/{run}/restore', [SettingsBackupController::class, 'restoreRun'])->middleware('throttle:10,1')->name('settings.backup.runs.restore');
        Route::post('/settings/backup/runs/{run}/cancel', [SettingsBackupController::class, 'cancelRun'])->name('settings.backup.runs.cancel');
    });

    // POST /logout is owned by Fortify (AuthenticatedSessionController@destroy).

    // Login/bank site-icon (BIMI/favicon) proxy: domain sent transiently, never
    // stored; SSRF-guarded. Retained for the Finance module (bank logos / partner
    // favicons).
    Route::get('/passwords/icon', [PasswordIconController::class, 'fetch'])->middleware('throttle:120,1')->name('passwords.icon');

    // Plaintext-relational Finance: invoices + partners + payment methods + bank
    // transactions + projects + categories as owner-scoped rows. The per-user
    // company profile (printed on invoices) stays in the user's settings.
    Route::middleware('module:finance')->group(function (): void {
        // The /finance page render is served by the SPA (see the catch-all);
        // only the owner-scoped data/mutation endpoints stay module-gated here.
        Route::get('/finance/data', [FinanceController::class, 'index'])->name('finance.data');
        // Read-only server-side analytics (source of truth for the stats UI).
        Route::get('/finance/reports', [FinanceReportController::class, 'reports'])->middleware('throttle:120,1')->name('finance.reports');
        Route::get('/finance/reports/account-vat', [FinanceReportController::class, 'accountVat'])->middleware('throttle:120,1')->name('finance.reports.account-vat');
        Route::get('/finance/reports/vat-advance', [FinanceReportController::class, 'vatAdvance'])->middleware('throttle:120,1')->name('finance.reports.vat-advance');
        Route::get('/finance/reports/euer', [FinanceReportController::class, 'euer'])->middleware('throttle:120,1')->name('finance.reports.euer');
        Route::get('/finance/duplicates', [FinanceReportController::class, 'duplicates'])->middleware('throttle:60,1')->name('finance.duplicates');
        Route::get('/finance/recurring', [FinanceReportController::class, 'recurring'])->middleware('throttle:60,1')->name('finance.recurring');
        Route::get('/finance/number-gaps', [FinanceReportController::class, 'numberGaps'])->middleware('throttle:60,1')->name('finance.number-gaps');
        Route::get('/finance/receipt-matches', [FinanceReportController::class, 'receiptMatches'])->middleware('throttle:60,1')->name('finance.receipt-matches');
        Route::get('/finance/category-suggestions', [FinanceReportController::class, 'categorySuggestions'])->middleware('throttle:60,1')->name('finance.category-suggestions');
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
        Route::get('/finance/projects/{project}/attachments', [FinanceController::class, 'projectAttachments'])->whereNumber('project')->middleware('throttle:600,1')->name('finance.projects.attachments');
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
        Route::post('/finance/invoices/{invoice}/email', [FinanceController::class, 'emailInvoice'])->whereNumber('invoice')->middleware('throttle:10,1')->name('finance.invoices.email');
        Route::post('/finance/invoices/{invoice}/storno', [FinanceController::class, 'stornoInvoice'])->whereNumber('invoice')->middleware('throttle:30,1')->name('finance.invoices.storno');
        Route::post('/finance/invoices/{invoice}/dun', [FinanceController::class, 'dunInvoice'])->whereNumber('invoice')->middleware('throttle:10,1')->name('finance.invoices.dun');
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
        // Standalone receipts ("Fremdbelege") — a receipt document without a bank transaction.
        Route::post('/finance/receipts', [FinanceController::class, 'storeReceipt'])->middleware('throttle:1200,1')->name('finance.receipts.store');
        Route::put('/finance/receipts/{receipt}', [FinanceController::class, 'updateReceipt'])->whereNumber('receipt')->middleware('throttle:600,1')->name('finance.receipts.update');
        Route::delete('/finance/receipts/{receipt}', [FinanceController::class, 'destroyStandaloneReceipt'])->whereNumber('receipt')->middleware('throttle:600,1')->name('finance.receipts.destroy');
        Route::post('/finance/receipts/{id}/restore', [FinanceController::class, 'restoreStandaloneReceipt'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.receipts.restore');
        Route::delete('/finance/receipts/{id}/force', [FinanceController::class, 'forceDeleteStandaloneReceipt'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.receipts.force');
        Route::get('/finance/receipts/{receipt}/raw', [FinanceController::class, 'receiptFile'])->whereNumber('receipt')->middleware('throttle:3000,1')->name('finance.receipts.raw');
        Route::delete('/finance/transactions/{transaction}/receipts/{receipt}', [FinanceController::class, 'destroyReceipt'])->whereNumber('transaction')->middleware('throttle:600,1')->name('finance.transactions.receipts.destroy');
    });

    // Deadlines read out of already-indexed document text. Not module-gated:
    // the findings come from files, mail, gallery and finance alike, so gating
    // it on one of them would hide the others' deadlines.
    Route::get('/deadlines', [DeadlineController::class, 'index'])->middleware('throttle:120,1')->name('deadlines.index');
    Route::put('/deadlines/{deadline}', [DeadlineController::class, 'update'])->middleware('throttle:120,1')->whereNumber('deadline')->name('deadlines.update');
    Route::post('/deadlines/scan', [DeadlineController::class, 'scan'])->middleware('throttle:6,1')->name('deadlines.scan');
    Route::redirect('/invoices', '/finance'); // old bookmarks

    // Per-user Files preferences (version-history depth). The edit page render is
    // served by the SPA (see the catch-all); the update endpoint stays here.
    Route::put('/settings/files', [SettingsFilesController::class, 'update'])->name('settings.files.update');

    // Files module — plaintext-relational: nested folders + files + version
    // history as rows, bytes plaintext on the files disk. The page render is
    // served by the SPA (see the catch-all); the data/mutation endpoints follow.
    Route::middleware('module:files')->group(function (): void {
        Route::get('/files/trash', [FilesController::class, 'trashed'])->name('files.rel.trash');
        Route::get('/files/activity', [FilesController::class, 'activity'])->middleware('throttle:600,1')->name('files.rel.activity');
        Route::get('/files/entries/{file}/activity', [FilesController::class, 'fileActivity'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.entries.activity');
        Route::get('/files/folders/{folder}/info', [FilesController::class, 'folderInfo'])->whereNumber('folder')->middleware('throttle:600,1')->name('files.rel.folders.info');
        Route::get('/files/entries/{file}/info', [FilesController::class, 'info'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.entries.info');
        Route::get('/files/entries/{file}/show', [FilesController::class, 'showEntry'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.entries.show');
        Route::get('/files/entries', [FilesController::class, 'index'])->name('files.rel.index');
        Route::get('/files/search', [FileSearchController::class, 'search'])->middleware('throttle:120,1')->name('files.rel.search');
        Route::get('/files/labels', [FilesController::class, 'labels'])->name('files.rel.labels');
        Route::post('/files/labels', [FilesController::class, 'storeLabel'])->middleware('throttle:600,1')->name('files.rel.labels.store');
        Route::put('/files/labels/{label}', [FilesController::class, 'updateLabel'])->whereNumber('label')->middleware('throttle:600,1')->name('files.rel.labels.update');
        Route::delete('/files/labels/{label}', [FilesController::class, 'destroyLabel'])->whereNumber('label')->middleware('throttle:600,1')->name('files.rel.labels.destroy');
        Route::post('/files/entries/{file}/labels', [FilesController::class, 'setFileLabels'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.entry.labels');
        Route::post('/files/entries', [FilesController::class, 'upload'])->middleware('throttle:1200,1')->name('files.rel.upload');
        Route::post('/files/entries/trash/empty', [FilesController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('files.rel.empty');
        Route::post('/files/zip', [FilesController::class, 'downloadZip'])->middleware('throttle:120,1')->name('files.zip');
        Route::post('/files/archive', [FilesController::class, 'createArchive'])->middleware('throttle:60,1')->name('files.archive');
        Route::post('/files/entries/{file}/extract', [FilesController::class, 'extractArchive'])->whereNumber('file')->middleware('throttle:60,1')->name('files.extract');
        Route::post('/files/entries/{file}/encrypt', [FilesController::class, 'encryptEntry'])->whereNumber('file')->middleware('throttle:60,1')->name('files.encrypt');
        Route::post('/files/entries/{file}/decrypt', [FilesController::class, 'decryptEntry'])->whereNumber('file')->middleware('throttle:60,1')->name('files.decrypt');
        Route::post('/files/folders/{folder}/encrypt', [FilesController::class, 'encryptFolder'])->whereNumber('folder')->middleware('throttle:30,1')->name('files.folders.encrypt');
        Route::get('/files/stats', [FilesController::class, 'stats'])->middleware('throttle:120,1')->name('files.stats');
        Route::get('/mounts', [MountController::class, 'index'])->name('mounts.index');
        Route::post('/mounts', [MountController::class, 'store'])->middleware('throttle:30,1')->name('mounts.store');
        Route::post('/mounts/test', [MountController::class, 'test'])->middleware('throttle:30,1')->name('mounts.test');
        Route::put('/mounts/{mount}', [MountController::class, 'update'])->whereNumber('mount')->middleware('throttle:30,1')->name('mounts.update');
        Route::delete('/mounts/{mount}', [MountController::class, 'destroy'])->whereNumber('mount')->middleware('throttle:30,1')->name('mounts.destroy');
        Route::get('/mounts/{mount}/list', [MountController::class, 'list'])->whereNumber('mount')->middleware('throttle:600,1')->name('mounts.list');
        Route::get('/mounts/{mount}/file', [MountController::class, 'download'])->whereNumber('mount')->middleware('throttle:600,1')->name('mounts.download');
        Route::post('/mounts/{mount}/upload', [MountController::class, 'upload'])->whereNumber('mount')->middleware('throttle:600,1')->name('mounts.upload');
        Route::post('/mounts/{mount}/mkdir', [MountController::class, 'mkdir'])->whereNumber('mount')->middleware('throttle:120,1')->name('mounts.mkdir');
        Route::post('/mounts/{mount}/delete', [MountController::class, 'deletePath'])->whereNumber('mount')->middleware('throttle:120,1')->name('mounts.delete-path');
        Route::put('/files/entries/{file}', [FilesController::class, 'update'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.update');
        Route::delete('/files/entries/{file}', [FilesController::class, 'destroy'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.destroy');
        Route::get('/files/entries/{file}/raw', [FilesController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('files.rel.raw');
        Route::get('/files/entries/{file}/thumb', [FilesController::class, 'thumb'])->whereNumber('file')->middleware('throttle:3000,1')->name('files.rel.thumb');
        Route::post('/files/entries/{file}/content', [FilesController::class, 'replaceContent'])->whereNumber('file')->middleware('throttle:1200,1')->name('files.rel.content');
        Route::post('/files/entries/{file}/toggle', [FilesController::class, 'toggle'])->whereNumber('file')->middleware('throttle:1200,1')->name('files.rel.toggle');
        Route::post('/files/entries/{file}/copy', [FilesController::class, 'copy'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.copy');
        Route::get('/files/entries/{file}/versions', [FilesController::class, 'versions'])->whereNumber('file')->name('files.rel.versions');
        Route::get('/files/entries/{file}/versions/{version}/raw', [FilesController::class, 'versionRaw'])->whereNumber(['file', 'version'])->middleware('throttle:3000,1')->name('files.rel.version.raw');
        Route::post('/files/entries/{file}/versions/{version}/restore', [FilesController::class, 'restoreVersion'])->whereNumber(['file', 'version'])->middleware('throttle:600,1')->name('files.rel.version.restore');
        Route::post('/files/entries/{id}/restore', [FilesController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('files.rel.restore');
        Route::delete('/files/entries/{id}/force', [FilesController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('files.rel.force');
        Route::get('/files/folders', [FilesController::class, 'folders'])->name('files.rel.folders');
        Route::post('/files/folders', [FilesController::class, 'storeFolder'])->middleware('throttle:600,1')->name('files.rel.folders.store');
        Route::put('/files/folders/{folder}', [FilesController::class, 'renameFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('files.rel.folders.update');
        Route::post('/files/folders/{folder}/copy', [FilesController::class, 'copyFolder'])->whereNumber('folder')->middleware('throttle:120,1')->name('files.rel.folders.copy');
        Route::post('/files/folders/{folder}/move', [FilesController::class, 'moveFolder'])->whereNumber('folder')->middleware('throttle:1200,1')->name('files.rel.folders.move');
        Route::delete('/files/folders/{folder}', [FilesController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('files.rel.folders.destroy');
        Route::post('/files/folders/{id}/restore', [FilesController::class, 'restoreFolder'])->whereNumber('id')->middleware('throttle:600,1')->name('files.rel.folders.restore');
        Route::delete('/files/folders/{id}/force', [FilesController::class, 'forceDeleteFolder'])->whereNumber('id')->middleware('throttle:600,1')->name('files.rel.folders.force');
        Route::post('/files/upload/chunk/init', [FilesController::class, 'chunkInit'])->middleware('throttle:600,1')->name('files.rel.chunk.init');
        Route::post('/files/upload/chunk/part', [FilesController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('files.rel.chunk.part');
        Route::post('/files/upload/chunk/complete', [FilesController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('files.rel.chunk.complete');
        Route::post('/files/upload/chunk/abort', [FilesController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('files.rel.chunk.abort');

        // Public share links (owner side) — plaintext /file-share/{token}.
        Route::get('/files/upload-links', [FilesController::class, 'uploadLinks'])->name('files.upload-links.index');
        Route::post('/files/upload-links', [FilesController::class, 'storeUploadLink'])->middleware('throttle:60,1')->name('files.upload-links.store');
        Route::delete('/files/upload-links/{link}', [FilesController::class, 'destroyUploadLink'])->whereNumber('link')->middleware('throttle:60,1')->name('files.upload-links.destroy');
        Route::get('/files/rel-shares', [FilesController::class, 'shares'])->name('files.rel.shares.index');
        Route::post('/files/rel-shares', [FilesController::class, 'storeShare'])->middleware('throttle:60,1')->name('files.rel.shares.store');
        Route::put('/files/rel-shares/{share}', [FilesController::class, 'updateShare'])->whereNumber('share')->middleware('throttle:60,1')->name('files.rel.shares.update');
        Route::delete('/files/rel-shares/{share}', [FilesController::class, 'destroyShare'])->whereNumber('share')->middleware('throttle:60,1')->name('files.rel.shares.destroy');

        // Cross-user folder sharing (owner side + shared-with-me member side).
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

    // Generic address autocomplete (forward geocode). Authenticated but NOT
    // module-gated — the calendar event editor AND the contacts map preview use it.
    Route::get('/geo/search', [GeoController::class, 'search'])->middleware('throttle:120,1')->name('geo.search');

    // Contacts + CardDAV (plaintext-relational). Static collection routes are
    // declared before /contacts/{contact} so they win over the model binding.
    Route::middleware('module:contacts')->group(function (): void {
        // The contacts index/create/duplicates/view/edit page renders are served
        // by the SPA (see the catch-all); the data endpoints remain here.
        Route::get('/contacts/data', [ContactController::class, 'data'])->name('contacts.data');
        // Sharing (address books) + birthday feed management
        Route::get('/contacts/shares', [ContactShareController::class, 'index'])->name('contacts.shares');
        Route::post('/contacts/shares', [ContactShareController::class, 'store'])->middleware('throttle:60,1')->name('contacts.shares.store');
        Route::delete('/contacts/shares/{share}', [ContactShareController::class, 'destroy'])->whereNumber('share')->middleware('throttle:60,1')->name('contacts.shares.destroy');
        Route::get('/contacts/shared-with-me', [ContactShareController::class, 'sharedWithMe'])->name('contacts.shared.index');
        Route::get('/contacts/shared-with-me/{share}', [ContactShareController::class, 'browse'])->whereNumber('share')->name('contacts.shared.browse');
        Route::get('/contacts/birthday-feed', [ContactShareController::class, 'feed'])->name('contacts.feed');
        Route::post('/contacts/birthday-feed', [ContactShareController::class, 'enableFeed'])->middleware('throttle:30,1')->name('contacts.feed.enable');
        Route::delete('/contacts/birthday-feed', [ContactShareController::class, 'disableFeed'])->middleware('throttle:30,1')->name('contacts.feed.disable');
        Route::get('/contacts/suggest', [ContactController::class, 'suggest'])->name('contacts.suggest');
        Route::get('/contacts/export', [ContactController::class, 'export'])->name('contacts.export');
        Route::post('/contacts/import', [ContactController::class, 'import'])->middleware('throttle:60,1')->name('contacts.import');
        Route::post('/contacts/settings', [ContactController::class, 'settings'])->middleware('throttle:600,1')->name('contacts.settings');
        Route::get('/contacts/sources/{source}/authorize', [ContactSyncSourceController::class, 'authorizeGoogle'])->name('contacts.sources.authorize');
        Route::get('/contacts/sources/google/callback', [ContactSyncSourceController::class, 'googleCallback'])->name('contacts.sources.google.callback');
        Route::delete('/contacts/bulk-destroy', [ContactController::class, 'bulkDestroy'])->middleware('throttle:600,1')->name('contacts.bulk-destroy');
        Route::post('/contacts', [ContactController::class, 'store'])->middleware('throttle:600,1')->name('contacts.store');

        // Duplicate detection + merge (page render served by the SPA).
        Route::get('/contacts/duplicates/data', [ContactDuplicateController::class, 'data'])->name('contacts.duplicates.data');
        Route::post('/contacts/duplicates/merge', [ContactDuplicateController::class, 'merge'])->middleware('throttle:120,1')->name('contacts.duplicates.merge');
        Route::post('/contacts/duplicates/dismiss', [ContactDuplicateController::class, 'dismiss'])->middleware('throttle:120,1')->name('contacts.duplicates.dismiss');

        // Per-contact. The show endpoint returns JSON (data route). Contacts use
        // UUID keys, so it is constrained to a UUID: this keeps non-UUID segments
        // (e.g. /contacts/duplicates, /contacts/create — SPA paths) from being
        // swallowed by the model binding so they fall through to the SPA catch-all.
        Route::get('/contacts/{contact}', [ContactController::class, 'show'])->whereUuid('contact')->name('contacts.show');
        Route::get('/contacts/{contact}/geo', [ContactController::class, 'geocode'])->middleware('throttle:120,1')->name('contacts.geo');
        Route::get('/contacts/{contact}/avatar', [ContactController::class, 'avatarImage'])->middleware('throttle:3000,1')->name('contacts.avatar');
        Route::patch('/contacts/{contact}/favorite', [ContactController::class, 'favorite'])->middleware('throttle:600,1')->name('contacts.favorite');
        Route::post('/contacts/{contact}/avatar', [ContactController::class, 'avatar'])->middleware('throttle:120,1')->name('contacts.avatar.upload');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->middleware('throttle:600,1')->name('contacts.update');
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->middleware('throttle:600,1')->name('contacts.destroy');

        // Address books + groups.
        Route::post('/address-books', [AddressBookController::class, 'store'])->middleware('throttle:600,1')->name('address-books.store');
        Route::put('/address-books/{addressBook}', [AddressBookController::class, 'update'])->middleware('throttle:600,1')->name('address-books.update');
        Route::delete('/address-books/{addressBook}', [AddressBookController::class, 'destroy'])->middleware('throttle:600,1')->name('address-books.destroy');
        Route::post('/contact-groups', [ContactGroupController::class, 'store'])->middleware('throttle:600,1')->name('contact-groups.store');
        Route::delete('/contact-groups/{group}', [ContactGroupController::class, 'destroy'])->middleware('throttle:600,1')->name('contact-groups.destroy');

        // CardDAV sync settings: the edit page render is served by the SPA; the
        // downloadable Apple enrollment profile (.mobileconfig) stays here.
        Route::get('/settings/contacts/profile', [SettingsContactsController::class, 'profile'])->name('settings.contacts.profile');
    });

    // Notes (plaintext-relational, Markdown). Static collection routes are
    // declared before /notes/{note} so they win over the model binding.

    Route::middleware('module:notes')->group(function (): void {
        Route::get('/notes/data', [NotesController::class, 'data'])->name('notes.data');
        Route::get('/notes/trash', [NotesController::class, 'trash'])->name('notes.trash');
        Route::get('/notes/search', [NotesController::class, 'search'])->middleware('throttle:120,1')->name('notes.search');
        Route::post('/notes', [NotesController::class, 'store'])->middleware('throttle:600,1')->name('notes.store');
        Route::post('/notes/folders', [NotesController::class, 'storeFolder'])->middleware('throttle:600,1')->name('notes.folders.store');
        Route::put('/notes/folders/{folder}', [NotesController::class, 'updateFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('notes.folders.update');
        Route::delete('/notes/folders/{folder}', [NotesController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('notes.folders.destroy');
        Route::post('/notes/folders/{id}/restore', [NotesController::class, 'restoreFolder'])->whereNumber('id')->middleware('throttle:600,1')->name('notes.folders.restore');
        Route::get('/notes/{note}', [NotesController::class, 'show'])->whereNumber('note')->name('notes.show');
        Route::get('/notes/{note}/backlinks', [NotesController::class, 'backlinks'])->whereNumber('note')->name('notes.backlinks');
        Route::get('/notes/{note}/export', [NotesController::class, 'export'])->whereNumber('note')->name('notes.export');
        Route::post('/notes/{note}/attachments', [NotesController::class, 'attach'])->whereNumber('note')->middleware('throttle:120,1')->name('notes.attachments.store');
        Route::post('/notes/{note}/attachments/from', [NotesController::class, 'attachFrom'])->whereNumber('note')->middleware('throttle:120,1')->name('notes.attachments.from');
        Route::get('/notes/{note}/attachments/{attachment}/raw', [NotesController::class, 'attachmentRaw'])->whereNumber('note')->whereNumber('attachment')->middleware('throttle:3000,1')->name('notes.attachments.raw');
        Route::delete('/notes/{note}/attachments/{attachment}', [NotesController::class, 'destroyAttachment'])->whereNumber('note')->whereNumber('attachment')->middleware('throttle:600,1')->name('notes.attachments.destroy');
        Route::put('/notes/{note}', [NotesController::class, 'update'])->whereNumber('note')->middleware('throttle:600,1')->name('notes.update');
        Route::patch('/notes/{note}/favorite', [NotesController::class, 'favorite'])->whereNumber('note')->middleware('throttle:600,1')->name('notes.favorite');
        Route::patch('/notes/{note}/pin', [NotesController::class, 'pin'])->whereNumber('note')->middleware('throttle:600,1')->name('notes.pin');
        Route::delete('/notes/{note}', [NotesController::class, 'destroy'])->whereNumber('note')->middleware('throttle:600,1')->name('notes.destroy');
        Route::post('/notes/{id}/restore', [NotesController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('notes.restore');
        Route::delete('/notes/{id}/force', [NotesController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('notes.force');
    });

    // Gallery (plaintext-relational, images). Static collection routes before /{photo}.
    Route::middleware('module:gallery')->group(function (): void {
        Route::get('/gallery/memories', [GalleryController::class, 'memories'])->middleware('throttle:120,1')->name('gallery.memories');
        Route::get('/gallery/data', [GalleryController::class, 'data'])->name('gallery.data');
        Route::get('/gallery/dates', [GalleryController::class, 'dates'])->name('gallery.dates');
        Route::get('/gallery/map', [GalleryController::class, 'map'])->name('gallery.map');
        Route::get('/gallery/search', [GalleryController::class, 'search'])->middleware('throttle:120,1')->name('gallery.search');
        Route::get('/gallery/duplicates', [GalleryController::class, 'duplicates'])->middleware('throttle:60,1')->name('gallery.duplicates');
        Route::get('/gallery/duplicates/formats', [GalleryController::class, 'formatDuplicates'])->middleware('throttle:60,1')->name('gallery.duplicates.formats');
        Route::get('/gallery/people', [GalleryPeopleController::class, 'people'])->name('gallery.people');
        Route::post('/gallery/people/merge', [GalleryPeopleController::class, 'merge'])->middleware('throttle:120,1')->name('gallery.people.merge');
        Route::get('/gallery/people/{person}', [GalleryPeopleController::class, 'person'])->whereNumber('person')->name('gallery.people.show');
        Route::put('/gallery/people/{person}', [GalleryPeopleController::class, 'personUpdate'])->whereNumber('person')->middleware('throttle:120,1')->name('gallery.people.update');
        Route::delete('/gallery/people/{person}', [GalleryPeopleController::class, 'personDestroy'])->whereNumber('person')->middleware('throttle:120,1')->name('gallery.people.destroy');
        Route::get('/gallery/{photo}/faces', [GalleryPeopleController::class, 'photoFaces'])->whereNumber('photo')->withTrashed()->name('gallery.photo.faces');
        Route::get('/gallery/faces/{face}/crop', [GalleryPeopleController::class, 'faceCrop'])->whereNumber('face')->middleware('throttle:6000,1')->name('gallery.faces.crop');
        Route::post('/gallery/faces/{face}/assign', [GalleryPeopleController::class, 'faceAssign'])->whereNumber('face')->middleware('throttle:300,1')->name('gallery.faces.assign');
        Route::post('/gallery/faces/{face}/hide', [GalleryPeopleController::class, 'faceHide'])->whereNumber('face')->middleware('throttle:300,1')->name('gallery.faces.hide');
        Route::get('/gallery/contacts/{contact}/photos', [GalleryPeopleController::class, 'contactPhotos'])->name('gallery.contact.photos');
        Route::post('/gallery/reprocess', [GalleryController::class, 'reprocess'])->middleware('throttle:60,1')->name('gallery.reprocess');
        Route::get('/gallery/ml-status', [GalleryController::class, 'mlStatus'])->name('gallery.ml-status');
        // Sharing — owner side (public album links + internal cross-user grants)
        Route::get('/gallery/shares', [GalleryShareController::class, 'index'])->name('gallery.shares');
        Route::post('/gallery/shares/public', [GalleryShareController::class, 'storePublic'])->middleware('throttle:60,1')->name('gallery.shares.public.store');
        Route::put('/gallery/shares/public/{share}', [GalleryShareController::class, 'updatePublic'])->whereNumber('share')->middleware('throttle:60,1')->name('gallery.shares.public.update');
        Route::delete('/gallery/shares/public/{share}', [GalleryShareController::class, 'destroyPublic'])->whereNumber('share')->middleware('throttle:60,1')->name('gallery.shares.public.destroy');
        Route::post('/gallery/shares/internal', [GalleryShareController::class, 'storeInternal'])->middleware('throttle:60,1')->name('gallery.shares.internal.store');
        Route::delete('/gallery/shares/internal/{share}', [GalleryShareController::class, 'destroyInternal'])->whereNumber('share')->middleware('throttle:60,1')->name('gallery.shares.internal.destroy');
        Route::get('/gallery/{photo}/comments', [GalleryCommentController::class, 'index'])->whereNumber('photo')->middleware('throttle:600,1')->name('gallery.comments.index');
        Route::post('/gallery/{photo}/comments', [GalleryCommentController::class, 'store'])->whereNumber('photo')->middleware('throttle:120,1')->name('gallery.comments.store');
        Route::delete('/gallery/comments/{comment}', [GalleryCommentController::class, 'destroy'])->whereNumber('comment')->middleware('throttle:120,1')->name('gallery.comments.destroy');
        Route::post('/gallery/{photo}/react', [GalleryCommentController::class, 'react'])->whereNumber('photo')->middleware('throttle:300,1')->name('gallery.react');
        Route::post('/gallery/upload-links', [GalleryShareController::class, 'storeUploadLink'])->middleware('throttle:30,1')->name('gallery.upload-links.store');
        Route::delete('/gallery/upload-links/{link}', [GalleryShareController::class, 'destroyUploadLink'])->whereNumber('link')->middleware('throttle:30,1')->name('gallery.upload-links.destroy');
        // Sharing — recipient side ("shared with me")
        Route::get('/gallery/shared-with-me', [SharedGalleryController::class, 'index'])->name('gallery.shared.index');
        Route::get('/gallery/shared-with-me/{share}', [SharedGalleryController::class, 'browse'])->whereNumber('share')->name('gallery.shared.browse');
        Route::get('/gallery/shared-with-me/{share}/photo/{photo}/thumb', [SharedGalleryController::class, 'thumb'])->whereNumber('share')->whereNumber('photo')->middleware('throttle:6000,1')->name('gallery.shared.thumb');
        Route::get('/gallery/shared-with-me/{share}/photo/{photo}/preview', [SharedGalleryController::class, 'preview'])->whereNumber('share')->whereNumber('photo')->middleware('throttle:6000,1')->name('gallery.shared.preview');
        Route::get('/gallery/shared-with-me/{share}/photo/{photo}/raw', [SharedGalleryController::class, 'raw'])->whereNumber('share')->whereNumber('photo')->middleware('throttle:3000,1')->name('gallery.shared.raw');
        Route::post('/gallery/shared-with-me/{share}/upload', [SharedGalleryController::class, 'upload'])->whereNumber('share')->middleware('throttle:1200,1')->name('gallery.shared.upload');
        Route::get('/gallery/trash', [GalleryController::class, 'trash'])->name('gallery.trash');
        Route::post('/gallery', [GalleryController::class, 'upload'])->middleware('throttle:1200,1')->name('gallery.upload');
        Route::post('/gallery/chunk/init', [GalleryController::class, 'chunkInit'])->middleware('throttle:600,1')->name('gallery.chunk.init');
        Route::post('/gallery/chunk/part', [GalleryController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('gallery.chunk.part');
        Route::post('/gallery/chunk/complete', [GalleryController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('gallery.chunk.complete');
        Route::post('/gallery/chunk/abort', [GalleryController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('gallery.chunk.abort');
        Route::get('/gallery/{photo}/raw', [GalleryController::class, 'raw'])->whereNumber('photo')->withTrashed()->middleware('throttle:3000,1')->name('gallery.raw');
        Route::get('/gallery/{photo}/thumb', [GalleryController::class, 'thumb'])->whereNumber('photo')->withTrashed()->middleware('throttle:6000,1')->name('gallery.thumb');
        Route::get('/gallery/{photo}/preview', [GalleryController::class, 'preview'])->whereNumber('photo')->withTrashed()->middleware('throttle:6000,1')->name('gallery.preview');
        Route::get('/gallery/{photo}/exif', [GalleryController::class, 'exif'])->whereNumber('photo')->middleware('throttle:600,1')->withTrashed()->name('gallery.exif');
        Route::patch('/gallery/{photo}/favorite', [GalleryController::class, 'favorite'])->whereNumber('photo')->middleware('throttle:600,1')->name('gallery.favorite');
        Route::patch('/gallery/{photo}/archive', [GalleryController::class, 'archive'])->whereNumber('photo')->middleware('throttle:600,1')->name('gallery.archive');
        Route::post('/gallery/bulk-archive', [GalleryController::class, 'bulkArchive'])->middleware('throttle:600,1')->name('gallery.bulk-archive');
        Route::post('/gallery/pair-live-photos', [GalleryController::class, 'pairLivePhotos'])->middleware('throttle:6,1')->name('gallery.pair-live');
        Route::put('/gallery/{photo}', [GalleryController::class, 'update'])->whereNumber('photo')->middleware('throttle:600,1')->name('gallery.update');
        Route::get('/gallery/{photo}/download', [GalleryController::class, 'download'])->whereNumber('photo')->withTrashed()->middleware('throttle:1200,1')->name('gallery.download');
        Route::get('/gallery/{photo}/play', [GalleryController::class, 'play'])->whereNumber('photo')->withTrashed()->middleware('throttle:3000,1')->name('gallery.play');
        Route::get('/gallery/{photo}/motion', [GalleryController::class, 'motion'])->whereNumber('photo')->withTrashed()->middleware('throttle:3000,1')->name('gallery.motion');
        Route::post('/gallery/{photo}/motion', [GalleryController::class, 'attachMotion'])->whereNumber('photo')->middleware('throttle:1200,1')->name('gallery.motion.attach');
        Route::delete('/gallery/{photo}', [GalleryController::class, 'destroy'])->whereNumber('photo')->middleware('throttle:600,1')->name('gallery.destroy');
        Route::post('/gallery/{id}/restore', [GalleryController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('gallery.restore');
        Route::delete('/gallery/{id}/force', [GalleryController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('gallery.force');
        Route::post('/gallery/trash/empty', [GalleryController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('gallery.empty');
        Route::post('/gallery/bulk-destroy', [GalleryController::class, 'bulkDestroy'])->middleware('throttle:600,1')->name('gallery.bulk-destroy');
        Route::get('/gallery/albums', [GalleryController::class, 'albums'])->name('gallery.albums');
        Route::post('/gallery/albums', [GalleryController::class, 'albumStore'])->middleware('throttle:120,1')->name('gallery.albums.store');
        Route::put('/gallery/albums/{album}', [GalleryController::class, 'albumUpdate'])->whereNumber('album')->middleware('throttle:120,1')->name('gallery.albums.update');
        Route::delete('/gallery/albums/{album}', [GalleryController::class, 'albumDestroy'])->whereNumber('album')->middleware('throttle:120,1')->name('gallery.albums.destroy');
        Route::post('/gallery/albums/{album}/photos', [GalleryController::class, 'albumAttach'])->whereNumber('album')->middleware('throttle:600,1')->name('gallery.albums.attach');
        Route::delete('/gallery/albums/{album}/photos', [GalleryController::class, 'albumDetach'])->whereNumber('album')->middleware('throttle:600,1')->name('gallery.albums.detach');
    });

    // Calendar + CalDAV (plaintext-relational). Static collection routes are
    // declared before /calendar/events/{event} so they win over model binding.
    Route::middleware('module:calendar')->group(function (): void {
        // The /calendar page render is served by the SPA (see the catch-all);
        // the data endpoints remain module-gated here.
        Route::get('/calendar/data', [CalendarController::class, 'data'])->name('calendar.data');
        // OpenHolidays proxies (SSRF-guarded) so the SPA selects load under CSP connect-src 'self'.
        Route::get('/calendar/holiday-countries', [CalendarController::class, 'holidayCountries'])->middleware('throttle:60,1')->name('calendar.holiday-countries');
        Route::get('/calendar/holiday-subdivisions', [CalendarController::class, 'holidaySubdivisions'])->middleware('throttle:60,1')->name('calendar.holiday-subdivisions');
        Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');
        Route::get('/calendar/export', [CalendarController::class, 'export'])->name('calendar.export');
        Route::post('/calendar/import', [CalendarController::class, 'import'])->middleware('throttle:60,1')->name('calendar.import');
        Route::post('/calendar/settings', [CalendarController::class, 'settings'])->middleware('throttle:600,1')->name('calendar.settings');
        Route::post('/calendar/events/{event}/rsvp', [CalendarController::class, 'rsvp'])->whereUuid('event')->middleware('throttle:120,1')->name('calendar.rsvp');
        Route::post('/calendar/imip', [CalendarController::class, 'imipIngest'])->middleware('throttle:60,1')->name('calendar.imip');
        Route::get('/calendar/free-busy', [CalendarController::class, 'freeBusy'])->middleware('throttle:600,1')->name('calendar.free-busy');
        Route::post('/calendar/slots', [CalendarController::class, 'slots'])->middleware('throttle:120,1')->name('calendar.slots');
        Route::get('/calendar/shares', [CalendarShareController::class, 'index'])->middleware('throttle:600,1')->name('calendar.shares.index');
        Route::post('/calendar/shares', [CalendarShareController::class, 'store'])->middleware('throttle:60,1')->name('calendar.shares.store');
        Route::delete('/calendar/shares/{share}', [CalendarShareController::class, 'destroy'])->whereNumber('share')->middleware('throttle:60,1')->name('calendar.shares.destroy');
        Route::post('/calendar/events', [CalendarController::class, 'store'])->middleware('throttle:600,1')->name('calendar.events.store');
        Route::get('/calendar/events/{event}', [CalendarController::class, 'show'])->name('calendar.events.show');
        Route::put('/calendar/events/{event}', [CalendarController::class, 'update'])->middleware('throttle:600,1')->name('calendar.events.update');
        Route::delete('/calendar/events/{event}', [CalendarController::class, 'destroy'])->middleware('throttle:600,1')->name('calendar.events.destroy');
        Route::get('/calendar/events/{event}/photos', [CalendarController::class, 'photos'])->middleware('throttle:120,1')->name('calendar.events.photos');
        Route::post('/calendar/events/{event}/exclude', [CalendarController::class, 'excludeOccurrence'])->middleware('throttle:600,1')->name('calendar.events.exclude');
        Route::put('/calendar/events/{event}/occurrence', [CalendarController::class, 'overrideOccurrence'])->middleware('throttle:600,1')->name('calendar.events.occurrence');

        // Tasks (VTODO). Static collection routes come before /calendar/todos/{todo}
        // so export/import/reorder are not captured by model binding.
        Route::get('/calendar/todos', [CalendarTodoController::class, 'index'])->name('calendar.todos');
        Route::get('/calendar/todos/export', [CalendarTodoController::class, 'export'])->name('calendar.todos.export');
        Route::post('/calendar/todos/import', [CalendarTodoController::class, 'import'])->middleware('throttle:60,1')->name('calendar.todos.import');
        Route::post('/calendar/todos/reorder', [CalendarTodoController::class, 'reorder'])->middleware('throttle:600,1')->name('calendar.todos.reorder');
        Route::post('/calendar/todos', [CalendarTodoController::class, 'store'])->middleware('throttle:600,1')->name('calendar.todos.store');
        Route::get('/calendar/todos/{todo}', [CalendarTodoController::class, 'show'])->name('calendar.todos.show');
        Route::put('/calendar/todos/{todo}', [CalendarTodoController::class, 'update'])->middleware('throttle:600,1')->name('calendar.todos.update');
        Route::delete('/calendar/todos/{todo}', [CalendarTodoController::class, 'destroy'])->middleware('throttle:600,1')->name('calendar.todos.destroy');
        Route::post('/calendar/todos/{todo}/complete', [CalendarTodoController::class, 'complete'])->middleware('throttle:600,1')->name('calendar.todos.complete');
        Route::post('/calendar/todos/{todo}/uncomplete', [CalendarTodoController::class, 'uncomplete'])->middleware('throttle:600,1')->name('calendar.todos.uncomplete');

        // Calendar collections.
        Route::post('/calendars', [CalendarBookController::class, 'store'])->middleware('throttle:600,1')->name('calendars.store');
        // Special (generated, read-only) calendars: create + (re)generate holidays/birthdays.
        Route::post('/calendars/special', [CalendarController::class, 'storeSpecial'])->middleware('throttle:60,1')->name('calendars.special');
        Route::post('/calendars/{calendar}/regenerate', [CalendarController::class, 'regenerate'])->middleware('throttle:60,1')->name('calendars.regenerate');
        Route::put('/calendars/{calendar}', [CalendarBookController::class, 'update'])->middleware('throttle:600,1')->name('calendars.update');
        Route::delete('/calendars/{calendar}', [CalendarBookController::class, 'destroy'])->middleware('throttle:600,1')->name('calendars.destroy');

        // CalDAV sync settings: the edit page render is served by the SPA; the
        // downloadable Apple enrollment profile (.mobileconfig) stays here.
        Route::get('/settings/calendar/profile', [SettingsCalendarController::class, 'profile'])->name('settings.calendar.profile');
    });

    // Mail archive (Phase 1). The /mail page render is served by the SPA (see -- banned-token-ok: pre-existing mail milestone label, unrelated to this change
    // the catch-all); the data endpoints are module-gated here. Guard-agnostic
    // controllers (shared 1:1 with /api/v1). Immutable archive: only seen/trash
    // toggle; the raw .eml is served sandboxed.
    Route::middleware('module:mail')->group(function (): void {
        Route::get('/mail/accounts', [MailAccountController::class, 'index'])->name('mail.accounts.index');
        Route::post('/mail/accounts/autoconfig', [MailAccountController::class, 'autoconfig'])->middleware('throttle:10,1')->name('mail.accounts.autoconfig');
        Route::post('/mail/accounts', [MailAccountController::class, 'store'])->middleware('throttle:60,1')->name('mail.accounts.store');
        Route::put('/mail/accounts/{account}', [MailAccountController::class, 'update'])->whereNumber('account')->middleware('throttle:60,1')->name('mail.accounts.update');
        Route::delete('/mail/accounts/{account}', [MailAccountController::class, 'destroy'])->whereNumber('account')->middleware('throttle:60,1')->name('mail.accounts.destroy');
        Route::post('/mail/accounts/{account}/sync', [MailAccountController::class, 'sync'])->whereNumber('account')->middleware('throttle:60,1')->name('mail.accounts.sync');
        Route::post('/mail/accounts/{account}/sync/cancel', [MailAccountController::class, 'cancelSync'])->whereNumber('account')->middleware('throttle:60,1')->name('mail.accounts.sync-cancel');
        Route::post('/mail/accounts/{account}/test', [MailAccountController::class, 'test'])->whereNumber('account')->middleware('throttle:6,1')->name('mail.accounts.test');
        Route::get('/mail/accounts/{account}/status', [MailAccountController::class, 'status'])->whereNumber('account')->name('mail.accounts.status');
        Route::get('/mail/accounts/{account}/logs', [MailLogController::class, 'index'])->whereNumber('account')->middleware('throttle:600,1')->name('mail.accounts.logs');
        Route::get('/mail/folders', [MailFolderController::class, 'index'])->middleware('throttle:600,1')->name('mail.folders.index');
        Route::get('/mail/messages', [MailMessageController::class, 'index'])->middleware('throttle:1200,1')->name('mail.messages.index');
        Route::get('/mail/messages/{message}', [MailMessageController::class, 'show'])->whereUuid('message')->middleware('throttle:1200,1')->name('mail.messages.show');
        Route::get('/mail/messages/{message}/body', [MailMessageController::class, 'body'])->whereUuid('message')->middleware('throttle:3000,1')->name('mail.messages.body');
        Route::post('/mail/messages/seen', [MailSeenController::class, 'update'])->middleware('throttle:120,1')->name('mail.messages.seen');
        Route::post('/mail/messages/flag', [MailFlagController::class, 'update'])->middleware('throttle:120,1')->name('mail.messages.flag');
        Route::post('/mail/messages/trash', [MailTrashController::class, 'trash'])->middleware('throttle:60,1')->name('mail.messages.trash');
        Route::post('/mail/messages/restore', [MailTrashController::class, 'restore'])->middleware('throttle:60,1')->name('mail.messages.restore');
        Route::post('/mail/messages/move', MailMoveController::class)->middleware('throttle:60,1')->name('mail.messages.move');
        Route::get('/mail/server-folders', [MailFolderAdminController::class, 'index'])->middleware('throttle:30,1')->name('mail.server-folders.index');
        Route::post('/mail/server-folders', [MailFolderAdminController::class, 'store'])->middleware('throttle:30,1')->name('mail.server-folders.store');
        Route::post('/mail/server-folders/rename', [MailFolderAdminController::class, 'rename'])->middleware('throttle:30,1')->name('mail.server-folders.rename');
        Route::post('/mail/server-folders/delete', [MailFolderAdminController::class, 'destroy'])->middleware('throttle:30,1')->name('mail.server-folders.destroy');
        Route::post('/mail/messages/labels', [MailLabelController::class, 'apply'])->middleware('throttle:120,1')->name('mail.messages.labels');
        Route::get('/mail/labels', [MailLabelController::class, 'index'])->name('mail.labels.index');
        Route::post('/mail/labels', [MailLabelController::class, 'store'])->middleware('throttle:60,1')->name('mail.labels.store');
        Route::put('/mail/labels/{label}', [MailLabelController::class, 'update'])->whereNumber('label')->middleware('throttle:60,1')->name('mail.labels.update');
        Route::delete('/mail/labels/{label}', [MailLabelController::class, 'destroy'])->whereNumber('label')->middleware('throttle:60,1')->name('mail.labels.destroy');
        Route::get('/mail/rules', [MailRuleController::class, 'index'])->name('mail.rules.index');
        Route::post('/mail/rules', [MailRuleController::class, 'store'])->middleware('throttle:60,1')->name('mail.rules.store');
        Route::put('/mail/rules/{rule}', [MailRuleController::class, 'update'])->whereNumber('rule')->middleware('throttle:60,1')->name('mail.rules.update');
        Route::delete('/mail/rules/{rule}', [MailRuleController::class, 'destroy'])->whereNumber('rule')->middleware('throttle:60,1')->name('mail.rules.destroy');
        Route::post('/mail/rules/apply', [MailRuleController::class, 'apply'])->middleware('throttle:6,1')->name('mail.rules.apply-all');
        Route::post('/mail/rules/{rule}/apply', [MailRuleController::class, 'apply'])->whereNumber('rule')->middleware('throttle:6,1')->name('mail.rules.apply');
        Route::get('/mail/saved-searches', [MailSavedSearchController::class, 'index'])->name('mail.saved-searches.index');
        Route::post('/mail/saved-searches', [MailSavedSearchController::class, 'store'])->middleware('throttle:60,1')->name('mail.saved-searches.store');
        Route::delete('/mail/saved-searches/{search}', [MailSavedSearchController::class, 'destroy'])->whereNumber('search')->middleware('throttle:60,1')->name('mail.saved-searches.destroy');
        Route::post('/mail/export', [MailExportController::class, 'export'])->middleware('throttle:30,1')->name('mail.export');
        Route::get('/mail/stats', [MailStatsController::class, 'index'])->middleware('throttle:120,1')->name('mail.stats');
        Route::post('/mail/messages/{message}/pushback', MailPushbackController::class)->whereUuid('message')->middleware('throttle:30,1')->name('mail.messages.pushback');
        Route::post('/mail/messages/{message}/delete-origin', MailDeleteOriginController::class)->whereUuid('message')->middleware('throttle:30,1')->name('mail.messages.delete-origin');
        Route::post('/mail/messages/compose', [MailSendController::class, 'compose'])->middleware('throttle:30,1')->name('mail.messages.compose');
        Route::post('/mail/messages/{message}/reply', [MailSendController::class, 'reply'])->whereUuid('message')->middleware('throttle:30,1')->name('mail.messages.reply');
        Route::post('/mail/messages/{message}/forward', [MailSendController::class, 'forward'])->whereUuid('message')->middleware('throttle:30,1')->name('mail.messages.forward');
        Route::get('/mail/attachments', [MailAttachmentController::class, 'index'])->middleware('throttle:600,1')->name('mail.attachments.index');
        Route::get('/mail/attachments/{attachment}/raw', [MailAttachmentController::class, 'raw'])->whereUuid('attachment')->middleware('throttle:3000,1')->name('mail.attachments.raw');
        Route::post('/mail/attachments/{attachment}/save', [MailAttachmentController::class, 'save'])->whereUuid('attachment')->middleware('throttle:60,1')->name('mail.attachments.save');
        Route::get('/mail/keys', [MailKeyController::class, 'index'])->name('mail.keys.index');
        Route::post('/mail/keys', [MailKeyController::class, 'store'])->middleware('throttle:60,1')->name('mail.keys.store');
        Route::post('/mail/keys/generate', [MailKeyController::class, 'generate'])->middleware('throttle:30,1')->name('mail.keys.generate');
        Route::delete('/mail/keys/{key}', [MailKeyController::class, 'destroy'])->whereNumber('key')->middleware('throttle:60,1')->name('mail.keys.destroy');
        Route::get('/mail/raw/{blob}', [MailBlobController::class, 'raw'])->whereUuid('blob')->middleware('throttle:3000,1')->name('mail.raw');
    });

    // Per-user company profile + invoice defaults (printed on every invoice). The
    // edit page render is served by the SPA; update + logo image stay here.
    Route::put('/settings/company', [SettingsCompanyController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/company/logo', [SettingsCompanyController::class, 'logo'])->name('settings.company.logo');

    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (used from the Finance receipt browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->middleware('throttle:60,1')->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->middleware('throttle:30,1')->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->middleware('throttle:20,1')->name('paperless.documents');
});

// Public SPA-shell page renders. These serve the SPA shell (no data — data comes
// from the gated /api/v1) and, crucially, preserve the named route entry points
// that kept, non-page controllers still reference for post-mutation redirects
// (e.g. settings.* saves) and invite consumption (finance.index). Auth is now
// enforced by the SPA router guard + the API, not by these page routes, so they
// are intentionally public: an unauthenticated visit returns the 200 shell and
// the SPA redirects to /login client-side.
$spa = static fn () => view('spa');
Route::get('/finance', $spa)->name('finance.index');
Route::get('/files', $spa)->name('files.index');
Route::get('/contacts', $spa)->name('contacts.index');
Route::get('/notes', $spa)->name('notes.index');
Route::get('/gallery', $spa)->name('gallery.index');
Route::get('/u/{token}', $spa)->name('upload-link.page');
Route::get('/calendar', $spa)->name('calendar.index');
Route::get('/mail', $spa)->name('mail.index');
Route::get('/profile', $spa)->name('profile');
Route::get('/settings/users', $spa)->name('settings.users');
Route::get('/settings/groups', $spa)->name('settings.groups');
Route::get('/settings/security', $spa)->name('settings.security.edit');
Route::get('/settings/notifications', $spa)->name('settings.notifications.edit');
Route::get('/settings/company', $spa)->name('settings.company.edit');
Route::get('/settings/files', $spa)->name('settings.files.edit');
Route::get('/settings/paperless', $spa)->name('settings.paperless.edit');
Route::get('/settings/backup', $spa)->name('settings.backup.index');

// Catch-all: every other GET UI path returns the SPA shell so vue-router can
// handle it client-side. GET-only, so POST/PUT/DELETE (Fortify auth + data
// mutations) are unaffected. The negative lookahead keeps real, non-SPA handlers
// (the token API, static assets, WebDAV, health/metrics, Sanctum, discovery,
// public file-share + invite links) from being shadowed by the shell.
Route::get('/{any}', $spa)
    ->where('any', '^(?!api/|build/|storage/|dav|up$|metrics$|sanctum/|\.well-known/|file-share/|invite/).*$')
    ->name('spa.catchall');
