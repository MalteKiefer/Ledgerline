<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DevicePairingController;
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
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\CompanyController as SettingsCompanyController;
use App\Http\Controllers\Settings\GroupsController as SettingsGroupsController;
use App\Http\Controllers\Settings\NotificationsController as SettingsNotificationsController;
use App\Http\Controllers\Settings\PaperlessController as SettingsPaperlessController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\Settings\SecurityLogController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\SystemController;
use App\Http\Controllers\Settings\UsersController as SettingsUsersController;
use App\Http\Controllers\ThemeController;
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

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
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
