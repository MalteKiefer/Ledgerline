<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceProductController;
use App\Http\Controllers\FinanceProjectPlanController;
use App\Http\Controllers\FinanceQuoteController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\InviteLinkController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaperlessController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\Settings\BackupController as SettingsBackupController;
use App\Http\Controllers\Settings\CompanyController as SettingsCompanyController;
use App\Http\Controllers\Settings\GroupsController as SettingsGroupsController;
use App\Http\Controllers\Settings\NotificationsController as SettingsNotificationsController;
use App\Http\Controllers\Settings\PaperlessController as SettingsPaperlessController;
use App\Http\Controllers\Settings\SecurityController as SettingsSecurityController;
use App\Http\Controllers\Settings\SystemController;
use App\Http\Controllers\Settings\UsersController as SettingsUsersController;
use App\Http\Controllers\ThemeController;
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

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::post('/theme', [ThemeController::class, 'update'])->name('theme.update');
    Route::post('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');
    // Profile page renders are served by the SPA (see the catch-all). Only the
    // data/artifact endpoints remain here.
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->middleware('throttle:30,1')->name('profile.avatar.store');
    Route::delete('/profile/avatar', [AvatarController::class, 'destroy'])->middleware('throttle:30,1')->name('profile.avatar.destroy');
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
        // Customer management: contact log + archive (hide from pickers without
        // deleting, because the partner's documents keep pointing at it).
        Route::post('/finance/partners/{partner}/archive', [FinanceController::class, 'archivePartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('finance.partners.archive');
        Route::get('/finance/partners/{partner}/notes', [FinanceController::class, 'partnerNotes'])->whereNumber('partner')->middleware('throttle:600,1')->name('finance.partners.notes');
        Route::post('/finance/partners/{partner}/notes', [FinanceController::class, 'storePartnerNote'])->whereNumber('partner')->middleware('throttle:600,1')->name('finance.partners.notes.store');
        Route::delete('/finance/partners/{partner}/notes/{note}', [FinanceController::class, 'destroyPartnerNote'])->whereNumber('partner')->whereNumber('note')->middleware('throttle:600,1')->name('finance.partners.notes.destroy');
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
        // Article catalogue (Warenverwaltung). Stock never moves through the
        // update path — only through the stock endpoint, which writes a movement.
        // Quotes (Angebote). Editable only while a draft; `send` gives it its
        // number, `convert` copies it into a draft invoice.
        Route::post('/finance/quotes', [FinanceQuoteController::class, 'store'])->middleware('throttle:600,1')->name('finance.quotes.store');
        Route::put('/finance/quotes/{quote}', [FinanceQuoteController::class, 'update'])->whereNumber('quote')->middleware('throttle:600,1')->name('finance.quotes.update');
        Route::post('/finance/quotes/{quote}/send', [FinanceQuoteController::class, 'send'])->whereNumber('quote')->middleware('throttle:120,1')->name('finance.quotes.send');
        Route::post('/finance/quotes/{quote}/decide', [FinanceQuoteController::class, 'decide'])->whereNumber('quote')->middleware('throttle:120,1')->name('finance.quotes.decide');
        Route::post('/finance/quotes/{quote}/convert', [FinanceQuoteController::class, 'convertToInvoice'])->whereNumber('quote')->middleware('throttle:120,1')->name('finance.quotes.convert');
        Route::post('/finance/quotes/{quote}/duplicate', [FinanceQuoteController::class, 'duplicate'])->whereNumber('quote')->middleware('throttle:120,1')->name('finance.quotes.duplicate');
        Route::delete('/finance/quotes/{quote}', [FinanceQuoteController::class, 'destroy'])->whereNumber('quote')->middleware('throttle:600,1')->name('finance.quotes.destroy');
        Route::post('/finance/quotes/{id}/restore', [FinanceQuoteController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.quotes.restore');
        Route::post('/finance/quotes/{quote}/pdf', [FinanceQuoteController::class, 'uploadPdf'])->whereNumber('quote')->middleware('throttle:120,1')->name('finance.quotes.pdf.store');
        Route::get('/finance/quotes/{quote}/pdf', [FinanceQuoteController::class, 'pdf'])->whereNumber('quote')->middleware('throttle:600,1')->name('finance.quotes.pdf');
        Route::post('/finance/quotes/{quote}/email', [FinanceController::class, 'emailQuote'])->whereNumber('quote')->middleware('throttle:10,1')->name('finance.quotes.email');
        Route::delete('/finance/quotes/{id}/force', [FinanceQuoteController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.quotes.force');
        Route::get('/finance/products/{product}/line', [FinanceQuoteController::class, 'lineFromProduct'])->whereNumber('product')->middleware('throttle:600,1')->name('finance.products.line');
        Route::post('/finance/products', [FinanceProductController::class, 'store'])->middleware('throttle:600,1')->name('finance.products.store');
        Route::put('/finance/products/{product}', [FinanceProductController::class, 'update'])->whereNumber('product')->middleware('throttle:600,1')->name('finance.products.update');
        Route::delete('/finance/products/{product}', [FinanceProductController::class, 'destroy'])->whereNumber('product')->middleware('throttle:600,1')->name('finance.products.destroy');
        Route::post('/finance/products/{id}/restore', [FinanceProductController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.products.restore');
        Route::delete('/finance/products/{id}/force', [FinanceProductController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('finance.products.force');
        Route::post('/finance/products/{product}/stock', [FinanceProductController::class, 'stock'])->whereNumber('product')->middleware('throttle:600,1')->name('finance.products.stock');
        Route::get('/finance/products/{product}/movements', [FinanceProductController::class, 'movements'])->whereNumber('product')->middleware('throttle:600,1')->name('finance.products.movements');
        // Project planning: tasks, hours, and the two conversions that make the
        // chain a chain (quote → project, worked hours → invoice).
        Route::get('/finance/projects/{project}/plan', [FinanceProjectPlanController::class, 'plan'])->whereNumber('project')->middleware('throttle:600,1')->name('finance.projects.plan');
        Route::post('/finance/projects/{project}/tasks', [FinanceProjectPlanController::class, 'storeTask'])->whereNumber('project')->middleware('throttle:600,1')->name('finance.projects.tasks.store');
        Route::post('/finance/projects/{project}/tasks/reorder', [FinanceProjectPlanController::class, 'reorderTasks'])->whereNumber('project')->middleware('throttle:600,1')->name('finance.projects.tasks.reorder');
        Route::put('/finance/project-tasks/{task}', [FinanceProjectPlanController::class, 'updateTask'])->whereNumber('task')->middleware('throttle:600,1')->name('finance.project-tasks.update');
        Route::delete('/finance/project-tasks/{task}', [FinanceProjectPlanController::class, 'destroyTask'])->whereNumber('task')->middleware('throttle:600,1')->name('finance.project-tasks.destroy');
        Route::post('/finance/projects/{project}/time', [FinanceProjectPlanController::class, 'storeTime'])->whereNumber('project')->middleware('throttle:600,1')->name('finance.projects.time.store');
        Route::put('/finance/time-entries/{entry}', [FinanceProjectPlanController::class, 'updateTime'])->whereNumber('entry')->middleware('throttle:600,1')->name('finance.time-entries.update');
        Route::delete('/finance/time-entries/{entry}', [FinanceProjectPlanController::class, 'destroyTime'])->whereNumber('entry')->middleware('throttle:600,1')->name('finance.time-entries.destroy');
        Route::post('/finance/projects/{project}/invoice-time', [FinanceProjectPlanController::class, 'invoiceTime'])->whereNumber('project')->middleware('throttle:120,1')->name('finance.projects.invoice-time');
        Route::post('/finance/quotes/{quote}/project', [FinanceProjectPlanController::class, 'projectFromQuote'])->whereNumber('quote')->middleware('throttle:120,1')->name('finance.quotes.project');
        Route::post('/finance/categories', [FinanceController::class, 'storeCategory'])->middleware('throttle:600,1')->name('finance.categories.store');
        Route::put('/finance/categories/{category}', [FinanceController::class, 'updateCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('finance.categories.update');
        Route::delete('/finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('finance.categories.destroy');

        // Invoice CRUD/finalize/storno/email/dun/upload routes used to live
        // here, calling legacy FinanceController methods removed in the Task
        // 17 cutover. Only the read-only PDF stream survives (GoBD requires a
        // pre-cutover invoice's document stay reachable) -- mirrors the
        // (also GET-only) api.finance.invoices.legacy-pdf route in
        // routes/api.php, kept under both prefixes exactly like the upload
        // route it replaces used to be, for the SPA's own session auth here.
        Route::get('/finance/invoices/{invoice}/pdf', [FinanceController::class, 'legacyInvoicePdf'])->whereNumber('invoice')->middleware('throttle:3000,1')->name('finance.invoices.pdf');

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
    // The edit page render is served by the SPA; update + logo image stay here.
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
Route::get('/profile', $spa)->name('profile');
Route::get('/settings/users', $spa)->name('settings.users');
Route::get('/settings/groups', $spa)->name('settings.groups');
Route::get('/settings/security', $spa)->name('settings.security.edit');
Route::get('/settings/notifications', $spa)->name('settings.notifications.edit');
Route::get('/settings/company', $spa)->name('settings.company.edit');
Route::get('/settings/paperless', $spa)->name('settings.paperless.edit');
Route::get('/settings/backup', $spa)->name('settings.backup.index');

// Catch-all: every other GET UI path returns the SPA shell so vue-router can
// handle it client-side. GET-only, so POST/PUT/DELETE (Fortify auth + data
// mutations) are unaffected. The negative lookahead keeps real, non-SPA handlers
// (the token API, static assets, health/metrics, Sanctum, discovery and invite
// links) from being shadowed by the shell.
Route::get('/{any}', $spa)
    ->where('any', '^(?!api/|build/|storage/|up$|metrics$|sanctum/|\.well-known/|invite/).*$')
    ->name('spa.catchall');
