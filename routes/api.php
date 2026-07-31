<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController as ApiBackupController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\GroupController as ApiGroupController;
use App\Http\Controllers\Api\InvoiceOcrController;
use App\Http\Controllers\Api\PaperlessController as ApiPaperlessController;
use App\Http\Controllers\Api\PasswordController as ApiPasswordController;
use App\Http\Controllers\Api\SecurityLogController as ApiSecurityLogController;
use App\Http\Controllers\Api\SettingsController as ApiSettingsController;
use App\Http\Controllers\Api\TwoFactorController as ApiTwoFactorController;
use App\Http\Controllers\Api\UsersController as ApiUsersController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ThemeController;
use App\Http\Middleware\UpdateTokenIp;
use Illuminate\Support\Facades\Route;

/*
 * Mobile API. Versioned under /api/v1; the native app authenticates with a
 * first-party Sanctum bearer obtained via QR device pairing. The data endpoints
 * reuse the web controllers. Per-route throttles mirror the web routes.
 */
Route::prefix('v1')->group(function (): void {
    // Public pairing exchange — the one-time code is the credential; hard-throttled.
    Route::middleware('throttle:auth-pair')->group(function (): void {
        Route::post('/auth/pair', [AuthController::class, 'pair'])->name('api.auth.pair');
        // Poll for the token via POST so the one-time code travels in the request
        // body, never in a URL/query string (which lands in access logs/proxies).
        Route::post('/auth/pair/collect', [AuthController::class, 'collect'])->name('api.auth.collect');
    });

    // Enforce the scoped 'device' ability minted at pairing (legacy '*' tokens
    // still pass) so a token's declared scope is actually checked.
    Route::middleware(['auth:sanctum', 'abilities:device', UpdateTokenIp::class])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.me');
        // Streams the signed-in user's stored avatar (same-origin, non-secret);
        // 404 when none stored. `me.user.has_avatar` tells the app whether to fetch it.
        Route::get('/avatar', AvatarController::class)->middleware('throttle:120,1')->name('api.avatar');
        Route::post('/device/heartbeat', [AuthController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.device.heartbeat');
        Route::delete('/auth/session', [AuthController::class, 'destroy'])->name('api.auth.destroy');

        // Transient server-side OCR of a raw receipt: returns line-structured text
        // only (recognition is client-side). Nothing is persisted/logged.
        Route::post('/invoices/ocr', [InvoiceOcrController::class, 'ocr'])->middleware(['throttle:120,1', 'module:finance'])->name('api.invoices.ocr');

        // Plaintext-relational Finance: invoices + partners + payment methods +
        // bank transactions + projects + categories as owner-scoped rows.
        Route::middleware('module:finance')->group(function (): void {
            Route::get('/finance/data', [FinanceController::class, 'index'])->name('api.finance.data');
            Route::get('/finance/reports', [FinanceReportController::class, 'reports'])->middleware('throttle:120,1')->name('api.finance.reports');
            Route::get('/finance/reports/account-vat', [FinanceReportController::class, 'accountVat'])->middleware('throttle:120,1')->name('api.finance.reports.account-vat');
            Route::get('/finance/reports/vat-advance', [FinanceReportController::class, 'vatAdvance'])->middleware('throttle:120,1')->name('api.finance.reports.vat-advance');
            Route::get('/finance/reports/euer', [FinanceReportController::class, 'euer'])->middleware('throttle:120,1')->name('api.finance.reports.euer');
            Route::get('/finance/duplicates', [FinanceReportController::class, 'duplicates'])->middleware('throttle:60,1')->name('api.finance.duplicates');
            Route::get('/finance/category-suggestions', [FinanceReportController::class, 'categorySuggestions'])->middleware('throttle:60,1')->name('api.finance.category-suggestions');
            Route::get('/finance/trash', [FinanceController::class, 'trash'])->name('api.finance.trash');

            Route::post('/finance/partners', [FinanceController::class, 'storePartner'])->middleware('throttle:600,1')->name('api.finance.partners.store');
            Route::put('/finance/partners/{partner}', [FinanceController::class, 'updatePartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('api.finance.partners.update');
            Route::delete('/finance/partners/{partner}', [FinanceController::class, 'destroyPartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('api.finance.partners.destroy');
            Route::post('/finance/partners/{id}/restore', [FinanceController::class, 'restorePartner'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.partners.restore');
            Route::delete('/finance/partners/{id}/force', [FinanceController::class, 'forceDeletePartner'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.partners.force');

            Route::post('/finance/payment-methods', [FinanceController::class, 'storePaymentMethod'])->middleware('throttle:600,1')->name('api.finance.payment-methods.store');
            Route::put('/finance/payment-methods/{paymentMethod}', [FinanceController::class, 'updatePaymentMethod'])->whereNumber('paymentMethod')->middleware('throttle:600,1')->name('api.finance.payment-methods.update');
            Route::delete('/finance/payment-methods/{paymentMethod}', [FinanceController::class, 'destroyPaymentMethod'])->whereNumber('paymentMethod')->middleware('throttle:600,1')->name('api.finance.payment-methods.destroy');
            Route::post('/finance/payment-methods/{id}/restore', [FinanceController::class, 'restorePaymentMethod'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.payment-methods.restore');
            Route::delete('/finance/payment-methods/{id}/force', [FinanceController::class, 'forceDeletePaymentMethod'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.payment-methods.force');

            Route::post('/finance/projects', [FinanceController::class, 'storeProject'])->middleware('throttle:600,1')->name('api.finance.projects.store');
            Route::put('/finance/projects/{project}', [FinanceController::class, 'updateProject'])->whereNumber('project')->middleware('throttle:600,1')->name('api.finance.projects.update');
            Route::post('/finance/projects/{project}/move', [FinanceController::class, 'moveProject'])->whereNumber('project')->middleware('throttle:1200,1')->name('api.finance.projects.move');
            Route::delete('/finance/projects/{project}', [FinanceController::class, 'destroyProject'])->whereNumber('project')->middleware('throttle:600,1')->name('api.finance.projects.destroy');
            Route::post('/finance/projects/{id}/restore', [FinanceController::class, 'restoreProject'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.projects.restore');
            Route::delete('/finance/projects/{id}/force', [FinanceController::class, 'forceDeleteProject'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.projects.force');

            Route::post('/finance/categories', [FinanceController::class, 'storeCategory'])->middleware('throttle:600,1')->name('api.finance.categories.store');
            Route::put('/finance/categories/{category}', [FinanceController::class, 'updateCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('api.finance.categories.update');
            Route::delete('/finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('api.finance.categories.destroy');

            Route::post('/finance/invoices', [FinanceController::class, 'storeInvoice'])->middleware('throttle:600,1')->name('api.finance.invoices.store');
            Route::put('/finance/invoices/{invoice}', [FinanceController::class, 'updateInvoice'])->whereNumber('invoice')->middleware('throttle:600,1')->name('api.finance.invoices.update');
            Route::post('/finance/invoices/{invoice}/finalize', [FinanceController::class, 'finalizeInvoice'])->whereNumber('invoice')->middleware('throttle:600,1')->name('api.finance.invoices.finalize');
            Route::post('/finance/invoices/{invoice}/email', [FinanceController::class, 'emailInvoice'])->whereNumber('invoice')->middleware('throttle:30,1')->name('api.finance.invoices.email');
            Route::post('/finance/invoices/{invoice}/storno', [FinanceController::class, 'stornoInvoice'])->whereNumber('invoice')->middleware('throttle:30,1')->name('api.finance.invoices.storno');
            Route::post('/finance/invoices/{invoice}/dun', [FinanceController::class, 'dunInvoice'])->whereNumber('invoice')->middleware('throttle:30,1')->name('api.finance.invoices.dun');
            Route::delete('/finance/invoices/{invoice}', [FinanceController::class, 'destroyInvoice'])->whereNumber('invoice')->middleware('throttle:600,1')->name('api.finance.invoices.destroy');
            Route::post('/finance/invoices/{id}/restore', [FinanceController::class, 'restoreInvoice'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.invoices.restore');
            Route::delete('/finance/invoices/{id}/force', [FinanceController::class, 'forceDeleteInvoice'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.invoices.force');
            Route::post('/finance/invoices/{invoice}/pdf', [FinanceController::class, 'uploadInvoicePdf'])->whereNumber('invoice')->middleware('throttle:1200,1')->name('api.finance.invoices.pdf.upload');
            Route::get('/finance/invoices/{invoice}/pdf', [FinanceController::class, 'invoicePdf'])->whereNumber('invoice')->middleware('throttle:3000,1')->name('api.finance.invoices.pdf');

            Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction'])->middleware('throttle:600,1')->name('api.finance.transactions.store');
            Route::post('/finance/transactions/bulk', [FinanceController::class, 'bulkTransactions'])->middleware('throttle:120,1')->name('api.finance.transactions.bulk');
            Route::put('/finance/transactions/{transaction}', [FinanceController::class, 'updateTransaction'])->whereNumber('transaction')->middleware('throttle:600,1')->name('api.finance.transactions.update');
            Route::delete('/finance/transactions/{transaction}', [FinanceController::class, 'destroyTransaction'])->whereNumber('transaction')->middleware('throttle:600,1')->name('api.finance.transactions.destroy');
            Route::post('/finance/transactions/{id}/restore', [FinanceController::class, 'restoreTransaction'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.transactions.restore');
            Route::delete('/finance/transactions/{id}/force', [FinanceController::class, 'forceDeleteTransaction'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.transactions.force');
            Route::post('/finance/transactions/{transaction}/receipts', [FinanceController::class, 'attachReceipt'])->whereNumber('transaction')->middleware('throttle:1200,1')->name('api.finance.transactions.receipts.store');
            Route::get('/finance/transactions/{transaction}/receipts/{receipt}/raw', [FinanceController::class, 'receiptRaw'])->whereNumber('transaction')->middleware('throttle:3000,1')->name('api.finance.transactions.receipts.raw');
            Route::delete('/finance/transactions/{transaction}/receipts/{receipt}', [FinanceController::class, 'destroyReceipt'])->whereNumber('transaction')->middleware('throttle:600,1')->name('api.finance.transactions.receipts.destroy');
        });

        // Per-user Paperless-ngx integration: cached term quick-picks, live term
        // creation, document forwarding, and cache sync. The /documents endpoint is
        // a transient-cleartext boundary (client posts bytes; server forwards to the
        // user's own Paperless and stores/logs nothing).
        Route::get('/paperless/terms', [ApiPaperlessController::class, 'terms'])->middleware('throttle:60,1')->name('api.paperless.terms');
        Route::post('/paperless/terms', [ApiPaperlessController::class, 'createTerm'])->middleware('throttle:30,1')->name('api.paperless.terms.create');
        Route::post('/paperless/documents', [ApiPaperlessController::class, 'submit'])->middleware('throttle:20,1')->name('api.paperless.documents');
        Route::post('/paperless/sync', [ApiPaperlessController::class, 'sync'])->middleware('throttle:20,1')->name('api.paperless.sync');

        // Per-user company profile + invoice defaults (non-secret business identity).
        Route::get('/company', [ApiCompanyController::class, 'show'])->name('api.company.show');
        Route::put('/company', [ApiCompanyController::class, 'update'])->middleware('throttle:60,1')->name('api.company.update');
        Route::get('/company/logo', [ApiCompanyController::class, 'logo'])->middleware('throttle:120,1')->name('api.company.logo');

        // Site-icon (BIMI/favicon) proxy: guard-agnostic, SSRF-guarded, nothing
        // stored server-side. Retained for the Finance module (bank logos /
        // partner favicons).
        Route::get('/passwords/icon', [PasswordIconController::class, 'fetch'])->middleware('throttle:1200,1')->name('api.passwords.icon');

        // Connected devices: list, revoke a device's token, request a remote wipe of a
        // lost device (the wipe flag is delivered on that device's next heartbeat).
        // Same guard-agnostic controller as the web routes.
        Route::get('/devices', [DevicePairingController::class, 'devices'])->name('api.devices.index');
        Route::delete('/devices/{token}', [DevicePairingController::class, 'revokeDevice'])->middleware('throttle:20,1')->name('api.devices.revoke');
        Route::post('/devices/{token}/wipe', [DevicePairingController::class, 'wipeDevice'])->middleware('throttle:20,1')->name('api.devices.wipe');
        // Owner-side device pairing (mobile-first user pairs a NEW device): generate a
        // code, poll its state, approve/reject the claim. Owner-scoped (authorizeOwner).
        Route::post('/device-pairings', [DevicePairingController::class, 'store'])->middleware('throttle:20,1')->name('api.device-pairings.store');
        Route::post('/device-pairings/cli', [DevicePairingController::class, 'storeCli'])->middleware('throttle:20,1')->name('api.device-pairings.cli');
        Route::get('/device-pairings/{devicePairing}', [DevicePairingController::class, 'show'])->name('api.device-pairings.show');
        Route::post('/device-pairings/{devicePairing}/approve', [DevicePairingController::class, 'approve'])->middleware('throttle:20,1')->name('api.device-pairings.approve');
        Route::post('/device-pairings/{devicePairing}/reject', [DevicePairingController::class, 'reject'])->middleware('throttle:20,1')->name('api.device-pairings.reject');

        // Notification centre: list (ETag/304), mark one read, mark all read.
        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('api.notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.notifications.read-all');

        // Account: GDPR data export (streamed), account deletion (crypto-shred), and
        // revoking a browser session. The redirect-based web controllers answer with
        // JSON here via expectsJson().
        Route::get('/account/export', [AccountController::class, 'export'])->middleware('throttle:6,1')->name('api.account.export');
        Route::delete('/account', [AccountController::class, 'destroy'])->name('api.account.destroy');
        Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->name('api.account.sessions.revoke');

        Route::post('/locale', [LocaleController::class, 'update'])->name('api.locale.update');
        Route::post('/theme', [ThemeController::class, 'update'])->name('api.theme.update');
        Route::post('/preferences', [PreferencesController::class, 'update'])->name('api.preferences.update');
        // Per-user non-display settings (contact notify channels + file version cap).
        Route::get('/settings', [ApiSettingsController::class, 'show'])->name('api.settings.show');
        Route::put('/settings', [ApiSettingsController::class, 'update'])->middleware('throttle:60,1')->name('api.settings.update');

        // 2FA management: enable, QR/secret, confirm, recovery codes, regenerate, disable.
        // Mirrors Fortify's web routes (/user/two-factor-*) for Sanctum bearer clients.
        Route::prefix('user')->name('api.user.')->group(function (): void {
            Route::prefix('two-factor')->name('2fa.')->group(function (): void {
                Route::post('/enable', [ApiTwoFactorController::class, 'enable'])->middleware('throttle:10,1')->name('enable');
                Route::get('/qr', [ApiTwoFactorController::class, 'qr'])->middleware('throttle:30,1')->name('qr');
                Route::post('/confirm', [ApiTwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
                Route::get('/recovery-codes', [ApiTwoFactorController::class, 'recoveryCodes'])->middleware('throttle:30,1')->name('recovery-codes');
                Route::post('/recovery-codes/regenerate', [ApiTwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:10,1')->name('recovery-codes.regenerate');
                Route::delete('/', [ApiTwoFactorController::class, 'disable'])->middleware('throttle:10,1')->name('disable');
            });

            Route::put('/password', [ApiPasswordController::class, 'update'])->middleware('throttle:10,1')->name('password');
            Route::post('/email/verify/resend', [ApiTwoFactorController::class, 'resendVerification'])->middleware('throttle:6,1')->name('email.verify.resend');
        });

        // Admin group management (workspace-wide limit templates + shareable flag).
        // Gated by the admin role on top of the device token; JSON mirror of the web
        // Settings/GroupsController. Non-secret metadata.
        Route::middleware('can:manage-global-settings')->prefix('groups')->name('api.groups.')->group(function (): void {
            Route::get('/', [ApiGroupController::class, 'index'])->name('index');
            Route::post('/', [ApiGroupController::class, 'store'])->middleware('throttle:60,1')->name('store');
            Route::put('/{group}', [ApiGroupController::class, 'update'])->middleware('throttle:60,1')->name('update');
            Route::delete('/{group}', [ApiGroupController::class, 'destroy'])->middleware('throttle:60,1')->name('destroy');
        });

        // Admin user management API (admin-gated, mirrors web Settings/UsersController).
        Route::middleware('can:manage-global-settings')->prefix('users')->name('api.users.')->group(function (): void {
            Route::get('/', [ApiUsersController::class, 'index'])->name('index');
            Route::post('/', [ApiUsersController::class, 'store'])->middleware('throttle:30,1')->name('store');
            Route::put('/{user}', [ApiUsersController::class, 'update'])->middleware('throttle:60,1')->name('update');
            Route::delete('/{user}', [ApiUsersController::class, 'destroy'])->middleware('throttle:10,1')->name('destroy');
            Route::post('/{user}/reset-password', [ApiUsersController::class, 'resetPassword'])->middleware('throttle:10,1')->name('reset-password');
            Route::post('/{user}/reset-2fa', [ApiUsersController::class, 'resetTwoFactor'])->middleware('throttle:10,1')->name('reset-2fa');
            Route::get('/{user}/avatar', [ApiUsersController::class, 'avatar'])->name('avatar');
            Route::post('/{user}/invite-link', [ApiUsersController::class, 'inviteLink'])->middleware('throttle:20,1')->name('invite-link');
        });

        // Admin security-log API — read-only, metadata-only audit trail.
        Route::middleware('can:manage-global-settings')->prefix('security-log')->name('api.security-log.')->group(function (): void {
            Route::get('/', [ApiSecurityLogController::class, 'index'])->middleware('throttle:60,1')->name('index');
            Route::get('/export', [ApiSecurityLogController::class, 'export'])->middleware('throttle:10,1')->name('export');
        });

        // Admin backup management — JSON mirror of web Settings/BackupController.
        // SECURITY: config (remote credentials) and passphrase (vault-key protection)
        // are encrypted:array / encrypted casts and are NEVER serialised in responses.
        Route::middleware('can:manage-global-settings')->prefix('backup')->name('api.backup.')->group(function (): void {
            // Destinations
            Route::get('/destinations', [ApiBackupController::class, 'destinations'])->name('destinations.index');
            Route::post('/destinations', [ApiBackupController::class, 'storeDestination'])->middleware('throttle:20,1')->name('destinations.store');
            Route::put('/destinations/{destination}', [ApiBackupController::class, 'updateDestination'])->middleware('throttle:20,1')->name('destinations.update');
            Route::delete('/destinations/{destination}', [ApiBackupController::class, 'destroyDestination'])->middleware('throttle:20,1')->name('destinations.destroy');
            Route::post('/destinations/test', [ApiBackupController::class, 'testDestination'])->middleware('throttle:20,1')->name('destinations.test');

            // Jobs
            Route::get('/jobs', [ApiBackupController::class, 'jobs'])->name('jobs.index');
            Route::post('/jobs', [ApiBackupController::class, 'storeJob'])->middleware('throttle:20,1')->name('jobs.store');
            Route::put('/jobs/{job}', [ApiBackupController::class, 'updateJob'])->middleware('throttle:20,1')->name('jobs.update');
            Route::delete('/jobs/{job}', [ApiBackupController::class, 'destroyJob'])->middleware('throttle:20,1')->name('jobs.destroy');
            Route::post('/jobs/{job}/run', [ApiBackupController::class, 'runNow'])->middleware('throttle:10,1')->name('jobs.run');

            // Runs
            Route::get('/runs', [ApiBackupController::class, 'runs'])->name('runs.index');
            Route::get('/runs/{run}/download', [ApiBackupController::class, 'downloadRun'])->name('runs.download');
            Route::post('/runs/{run}/verify', [ApiBackupController::class, 'verifyRun'])->middleware('throttle:10,1')->name('runs.verify');
            Route::post('/runs/{run}/cancel', [ApiBackupController::class, 'cancelRun'])->middleware('throttle:20,1')->name('runs.cancel');
            Route::post('/runs/{run}/decrypt', [ApiBackupController::class, 'decryptRun'])->middleware('throttle:10,1')->name('runs.decrypt');
        });
    });
});
