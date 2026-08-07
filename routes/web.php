<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MetricsController;
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
use App\Http\Controllers\WebDavAccessController;
use App\Http\Controllers\WebDavController;
use Illuminate\Support\Facades\Route;

// The root forwards to Finance (the app is finance-only); unauthenticated
// visitors are then redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('finance.index'));

// Prometheus metrics for external scraping — no session; guarded by its own
// token (OPS_METRICS_TOKEN) and disabled when unset. Rate-limited.
Route::get('/metrics', [MetricsController::class, 'index'])->middleware('throttle:60,1')->name('metrics');

// First-party auth (login, registration, password reset, email verification,
// two-factor) is owned by Laravel Fortify — see FortifyServiceProvider.

// Mail-independent invite / password-reset links: public consumption. The token
// is a hashed, single-use, expiring secret in the URL; the route is throttled and
// verifies it in constant time. Consuming it sets the user's password.
Route::get('/invite/{invite}/{token}', [InviteLinkController::class, 'show'])->middleware('throttle:20,1')->name('invite.show');
Route::post('/invite/{invite}/{token}', [InviteLinkController::class, 'store'])->middleware('throttle:10,1')->name('invite.store');

// Public, unauthenticated plaintext file-share links (optional password gate).
Route::prefix('file-share/{token}')->name('public.file-share.')->group(function (): void {
    Route::get('/', [PublicFileShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
    Route::post('/unlock', [PublicFileShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
    Route::get('/manifest', [PublicFileShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
    Route::get('/file/{file}/raw', [PublicFileShareController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('file.raw');
});

// WebDAV endpoint — Sabre handles auth (HTTP Basic) + the DAV protocol itself.
// Registered for every WebDAV verb (PROPFIND/MKCOL/MOVE/… are not covered by
// Route::any). CSRF is excluded for dav/* in bootstrap/app.php.
Route::match(
    ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS', 'HEAD', 'PROPFIND', 'PROPPATCH', 'MKCOL', 'MOVE', 'COPY', 'LOCK', 'UNLOCK', 'REPORT'],
    '/dav/{path?}',
    WebDavController::class
)->where('path', '.*')->name('dav');

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
    Route::post('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    // WebDAV app-specific password (mount Files as a network drive).
    Route::put('/profile/webdav', [WebDavAccessController::class, 'update'])->middleware('throttle:20,1')->name('profile.webdav.update');
    Route::delete('/profile/webdav', [WebDavAccessController::class, 'destroy'])->middleware('throttle:20,1')->name('profile.webdav.destroy');
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
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->middleware('throttle:30,1')->name('profile.avatar.store');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->middleware('throttle:30,1')->name('profile.avatar.destroy');
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

    // Paperless-ngx: per-user integration (each user's own instance URL + token).
    Route::get('/settings/paperless', [SettingsPaperlessController::class, 'edit'])->name('settings.paperless.edit');
    Route::put('/settings/paperless', [SettingsPaperlessController::class, 'update'])->name('settings.paperless.update');
    Route::post('/settings/paperless/test', [SettingsPaperlessController::class, 'test'])->middleware('throttle:20,1')->name('settings.paperless.test');
    Route::post('/settings/paperless/sync', [SettingsPaperlessController::class, 'sync'])->middleware('throttle:20,1')->name('settings.paperless.sync');

    // Non-personal, workspace-wide settings — restricted to users with the admin
    // role (see User::managesGlobalSettings / the manage-global-settings gate).
    Route::middleware('can:manage-global-settings')->group(function (): void {
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

        // Workspace security policy (per-user paired-device cap).
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
        Route::get('/finance', [FinanceController::class, 'page'])->name('finance.index');
        Route::get('/finance/data', [FinanceController::class, 'index'])->name('finance.data');
        // Read-only server-side analytics (source of truth for the stats UI).
        Route::get('/finance/reports', [FinanceReportController::class, 'reports'])->middleware('throttle:120,1')->name('finance.reports');
        Route::get('/finance/reports/account-vat', [FinanceReportController::class, 'accountVat'])->middleware('throttle:120,1')->name('finance.reports.account-vat');
        Route::get('/finance/reports/vat-advance', [FinanceReportController::class, 'vatAdvance'])->middleware('throttle:120,1')->name('finance.reports.vat-advance');
        Route::get('/finance/reports/euer', [FinanceReportController::class, 'euer'])->middleware('throttle:120,1')->name('finance.reports.euer');
        Route::get('/finance/duplicates', [FinanceReportController::class, 'duplicates'])->middleware('throttle:60,1')->name('finance.duplicates');
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
    Route::redirect('/invoices', '/finance'); // old bookmarks

    // Per-user Files preferences (version-history depth).
    Route::get('/settings/files', [SettingsFilesController::class, 'edit'])->name('settings.files.edit');
    Route::put('/settings/files', [SettingsFilesController::class, 'update'])->name('settings.files.update');

    // Files module — plaintext-relational: nested folders + files + version
    // history as rows, bytes plaintext on the files disk. Hybrid-rendered page.
    Route::get('/files', [FilesController::class, 'page'])->middleware('module:files')->name('files.index');
    Route::middleware('module:files')->group(function (): void {
        Route::get('/files/trash', [FilesController::class, 'trashed'])->name('files.rel.trash');
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
        Route::get('/files/stats', [FilesController::class, 'stats'])->middleware('throttle:120,1')->name('files.stats');
        Route::put('/files/entries/{file}', [FilesController::class, 'update'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.update');
        Route::delete('/files/entries/{file}', [FilesController::class, 'destroy'])->whereNumber('file')->middleware('throttle:600,1')->name('files.rel.destroy');
        Route::get('/files/entries/{file}/raw', [FilesController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('files.rel.raw');
        Route::get('/files/entries/{file}/thumb', [FilesController::class, 'thumb'])->whereNumber('file')->middleware('throttle:3000,1')->name('files.rel.thumb');
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

    // Per-user company profile + invoice defaults (printed on every invoice).
    Route::get('/settings/company', [SettingsCompanyController::class, 'edit'])->name('settings.company.edit');
    Route::put('/settings/company', [SettingsCompanyController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/company/logo', [SettingsCompanyController::class, 'logo'])->name('settings.company.logo');

    // Paperless transfer modal: cached quick-pick terms, term creation and
    // document upload (used from the Finance receipt browser).
    Route::get('/paperless/terms', [PaperlessController::class, 'terms'])->middleware('throttle:60,1')->name('paperless.terms');
    Route::post('/paperless/terms', [PaperlessController::class, 'createTerm'])->middleware('throttle:30,1')->name('paperless.terms.create');
    Route::post('/paperless/documents', [PaperlessController::class, 'submit'])->middleware('throttle:20,1')->name('paperless.documents');
});
