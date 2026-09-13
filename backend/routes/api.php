<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController as ApiBackupController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\DevicePushEndpointController;
use App\Http\Controllers\Api\DockerController as ApiDockerController;
use App\Http\Controllers\Api\GroupController as ApiGroupController;
use App\Http\Controllers\Api\InviteLinkController as ApiInviteLinkController;
use App\Http\Controllers\Api\InvoiceOcrController;
use App\Http\Controllers\Api\LimitsController as ApiLimitsController;
use App\Http\Controllers\Api\NotificationsController as ApiNotificationsController;
use App\Http\Controllers\Api\PaperlessController as ApiPaperlessController;
use App\Http\Controllers\Api\PasskeyController as ApiPasskeyController;
use App\Http\Controllers\Api\PasswordController as ApiPasswordController;
use App\Http\Controllers\Api\SecurityController as ApiSecurityController;
use App\Http\Controllers\Api\SecurityLogController as ApiSecurityLogController;
use App\Http\Controllers\Api\SecurityPortalController;
use App\Http\Controllers\Api\SpaAuthController;
use App\Http\Controllers\Api\SystemController as ApiSystemController;
use App\Http\Controllers\Api\TwoFactorController as ApiTwoFactorController;
use App\Http\Controllers\Api\UsersController as ApiUsersController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceProductController;
use App\Http\Controllers\FinanceProjectPlanController;
use App\Http\Controllers\FinanceQuoteController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ThemeController;
use App\Http\Middleware\EnsureTwoFactorEnrolled;
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

    // Backend-agnostic browser login: email+password (+2FA) → bearer token, so the
    // SPA never depends on a Laravel session cookie (portable to a future Go API).
    Route::post('/auth/login', [SpaAuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');

    // Passwordless sign-in with a passkey / hardware key (public). Mints a device
    // token on a valid assertion, like /auth/login.
    Route::post('/auth/passkey/options', [ApiPasskeyController::class, 'loginOptions'])->middleware('throttle:30,1')->name('api.auth.passkey.options');
    Route::post('/auth/passkey/verify', [ApiPasskeyController::class, 'loginVerify'])->middleware('throttle:30,1')->name('api.auth.passkey.verify');

    // Public account lifecycle (no auth). Mirrors the web Fortify pipeline via the
    // same actions. forgot-password always answers generically (no enumeration);
    // register is gated by the workspace allow_registration flag (403 when off).
    Route::post('/auth/forgot-password', [SpaAuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('api.auth.forgot-password');
    Route::post('/auth/reset-password', [SpaAuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('api.auth.reset-password');
    Route::post('/auth/register', [SpaAuthController::class, 'register'])->middleware('throttle:6,1')->name('api.auth.register');

    // Public, unauthenticated invite / password-reset link consumption. The admin
    // CREATE side is /api/v1/users/{user}/invite-link; this is the consume side.
    // show reports validity as JSON (never a redirect); store sets the password and
    // mints a bearer (rather than a session login). Hashed single-use expiring token.
    Route::get('/invite/{invite}/{token}', [ApiInviteLinkController::class, 'show'])->middleware('throttle:20,1')->name('api.invite.show');
    Route::post('/invite/{invite}/{token}', [ApiInviteLinkController::class, 'store'])->middleware('throttle:20,1')->name('api.invite.store');

    // Enforce the scoped 'device' ability minted at pairing (legacy '*' tokens
    // still pass) so a token's declared scope is actually checked.
    Route::middleware(['auth:sanctum', 'abilities:device', UpdateTokenIp::class, EnsureTwoFactorEnrolled::class])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.me');
        Route::post('/auth/logout', [SpaAuthController::class, 'logout'])->name('api.auth.logout');
        // Streams the signed-in user's stored avatar (same-origin, non-secret);
        // 404 when none stored. `me.user.has_avatar` tells the app whether to fetch it.
        Route::get('/avatar', AvatarController::class)->middleware('throttle:120,1')->name('api.avatar');
        Route::post('/avatar', [AvatarController::class, 'store'])->middleware('throttle:30,1')->name('api.avatar.store');
        // Per-device UnifiedPush endpoint (tied to the calling device token).
        Route::post('/device/push-endpoint', [DevicePushEndpointController::class, 'store'])->middleware('throttle:30,1')->name('api.device.push-endpoint.store');
        Route::delete('/device/push-endpoint', [DevicePushEndpointController::class, 'destroy'])->middleware('throttle:30,1')->name('api.device.push-endpoint.destroy');
        Route::delete('/avatar', [AvatarController::class, 'destroy'])->middleware('throttle:30,1')->name('api.avatar.destroy');
        Route::post('/device/heartbeat', [AuthController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.device.heartbeat');
        Route::delete('/auth/session', [AuthController::class, 'destroy'])->name('api.auth.destroy');

        // Transient server-side OCR of a raw receipt: returns line-structured text
        // only (recognition is client-side). Nothing is persisted/logged. 120/min
        // (the original v1.506.88 design value) — the endpoint drives the receipt
        // inbox's own bulk-upload feature (drop many files at once), which fires
        // one OCR call per file sequentially with no inter-request delay; a real
        // batch of ~27 mostly text-layer PDFs (near-instant per call) blew through
        // a since-tightened 20/min within seconds, well before finishing.
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
            Route::get('/finance/recurring', [FinanceReportController::class, 'recurring'])->middleware('throttle:60,1')->name('api.finance.recurring');
            Route::get('/finance/number-gaps', [FinanceReportController::class, 'numberGaps'])->middleware('throttle:60,1')->name('api.finance.number-gaps');
            Route::get('/finance/receipt-matches', [FinanceReportController::class, 'receiptMatches'])->middleware('throttle:60,1')->name('api.finance.receipt-matches');
            Route::get('/finance/category-suggestions', [FinanceReportController::class, 'categorySuggestions'])->middleware('throttle:60,1')->name('api.finance.category-suggestions');
            Route::get('/finance/trash', [FinanceController::class, 'trash'])->name('api.finance.trash');

            Route::post('/finance/partners', [FinanceController::class, 'storePartner'])->middleware('throttle:600,1')->name('api.finance.partners.store');
            Route::put('/finance/partners/{partner}', [FinanceController::class, 'updatePartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('api.finance.partners.update');
            Route::delete('/finance/partners/{partner}', [FinanceController::class, 'destroyPartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('api.finance.partners.destroy');
            Route::post('/finance/partners/{id}/restore', [FinanceController::class, 'restorePartner'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.partners.restore');
            Route::delete('/finance/partners/{id}/force', [FinanceController::class, 'forceDeletePartner'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.partners.force');

            // Customer management: contact log + archive (hide from pickers without
            // deleting, because the partner's documents keep pointing at it).
            Route::post('/finance/partners/{partner}/archive', [FinanceController::class, 'archivePartner'])->whereNumber('partner')->middleware('throttle:600,1')->name('api.finance.partners.archive');
            Route::get('/finance/partners/{partner}/notes', [FinanceController::class, 'partnerNotes'])->whereNumber('partner')->middleware('throttle:600,1')->name('api.finance.partners.notes');
            Route::post('/finance/partners/{partner}/notes', [FinanceController::class, 'storePartnerNote'])->whereNumber('partner')->middleware('throttle:600,1')->name('api.finance.partners.notes.store');
            Route::delete('/finance/partners/{partner}/notes/{note}', [FinanceController::class, 'destroyPartnerNote'])->whereNumber('partner')->whereNumber('note')->middleware('throttle:600,1')->name('api.finance.partners.notes.destroy');
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

            // Article catalogue (Warenverwaltung). Stock never moves through the
            // update path — only through the stock endpoint, which writes a movement.
            // Quotes (Angebote). Editable only while a draft; `send` gives it its
            // number, `convert` copies it into a draft invoice.
            Route::post('/finance/quotes', [FinanceQuoteController::class, 'store'])->middleware('throttle:600,1')->name('api.finance.quotes.store');
            Route::put('/finance/quotes/{quote}', [FinanceQuoteController::class, 'update'])->whereNumber('quote')->middleware('throttle:600,1')->name('api.finance.quotes.update');
            Route::post('/finance/quotes/{quote}/send', [FinanceQuoteController::class, 'send'])->whereNumber('quote')->middleware('throttle:120,1')->name('api.finance.quotes.send');
            Route::post('/finance/quotes/{quote}/decide', [FinanceQuoteController::class, 'decide'])->whereNumber('quote')->middleware('throttle:120,1')->name('api.finance.quotes.decide');
            Route::post('/finance/quotes/{quote}/convert', [FinanceQuoteController::class, 'convertToInvoice'])->whereNumber('quote')->middleware('throttle:120,1')->name('api.finance.quotes.convert');
            Route::post('/finance/quotes/{quote}/duplicate', [FinanceQuoteController::class, 'duplicate'])->whereNumber('quote')->middleware('throttle:120,1')->name('api.finance.quotes.duplicate');
            Route::delete('/finance/quotes/{quote}', [FinanceQuoteController::class, 'destroy'])->whereNumber('quote')->middleware('throttle:600,1')->name('api.finance.quotes.destroy');
            Route::post('/finance/quotes/{id}/restore', [FinanceQuoteController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.quotes.restore');
            Route::post('/finance/quotes/{quote}/pdf', [FinanceQuoteController::class, 'uploadPdf'])->whereNumber('quote')->middleware('throttle:120,1')->name('api.finance.quotes.pdf.store');
            Route::get('/finance/quotes/{quote}/pdf', [FinanceQuoteController::class, 'pdf'])->whereNumber('quote')->middleware('throttle:600,1')->name('api.finance.quotes.pdf');
            Route::post('/finance/quotes/{quote}/email', [FinanceController::class, 'emailQuote'])->whereNumber('quote')->middleware('throttle:10,1')->name('api.finance.quotes.email');
            Route::delete('/finance/quotes/{id}/force', [FinanceQuoteController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.quotes.force');
            Route::get('/finance/products/{product}/line', [FinanceQuoteController::class, 'lineFromProduct'])->whereNumber('product')->middleware('throttle:600,1')->name('api.finance.products.line');
            Route::post('/finance/products', [FinanceProductController::class, 'store'])->middleware('throttle:600,1')->name('api.finance.products.store');
            Route::put('/finance/products/{product}', [FinanceProductController::class, 'update'])->whereNumber('product')->middleware('throttle:600,1')->name('api.finance.products.update');
            Route::delete('/finance/products/{product}', [FinanceProductController::class, 'destroy'])->whereNumber('product')->middleware('throttle:600,1')->name('api.finance.products.destroy');
            Route::post('/finance/products/{id}/restore', [FinanceProductController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.products.restore');
            Route::delete('/finance/products/{id}/force', [FinanceProductController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.products.force');
            Route::post('/finance/products/{product}/stock', [FinanceProductController::class, 'stock'])->whereNumber('product')->middleware('throttle:600,1')->name('api.finance.products.stock');
            Route::get('/finance/products/{product}/movements', [FinanceProductController::class, 'movements'])->whereNumber('product')->middleware('throttle:600,1')->name('api.finance.products.movements');
            // Project planning: tasks, hours, and the two conversions that make the
            // chain a chain (quote → project, worked hours → invoice).
            Route::get('/finance/projects/{project}/plan', [FinanceProjectPlanController::class, 'plan'])->whereNumber('project')->middleware('throttle:600,1')->name('api.finance.projects.plan');
            Route::post('/finance/projects/{project}/tasks', [FinanceProjectPlanController::class, 'storeTask'])->whereNumber('project')->middleware('throttle:600,1')->name('api.finance.projects.tasks.store');
            Route::post('/finance/projects/{project}/tasks/reorder', [FinanceProjectPlanController::class, 'reorderTasks'])->whereNumber('project')->middleware('throttle:600,1')->name('api.finance.projects.tasks.reorder');
            Route::put('/finance/project-tasks/{task}', [FinanceProjectPlanController::class, 'updateTask'])->whereNumber('task')->middleware('throttle:600,1')->name('api.finance.project-tasks.update');
            Route::delete('/finance/project-tasks/{task}', [FinanceProjectPlanController::class, 'destroyTask'])->whereNumber('task')->middleware('throttle:600,1')->name('api.finance.project-tasks.destroy');
            Route::post('/finance/projects/{project}/time', [FinanceProjectPlanController::class, 'storeTime'])->whereNumber('project')->middleware('throttle:600,1')->name('api.finance.projects.time.store');
            Route::put('/finance/time-entries/{entry}', [FinanceProjectPlanController::class, 'updateTime'])->whereNumber('entry')->middleware('throttle:600,1')->name('api.finance.time-entries.update');
            Route::delete('/finance/time-entries/{entry}', [FinanceProjectPlanController::class, 'destroyTime'])->whereNumber('entry')->middleware('throttle:600,1')->name('api.finance.time-entries.destroy');
            Route::post('/finance/projects/{project}/invoice-time', [FinanceProjectPlanController::class, 'invoiceTime'])->whereNumber('project')->middleware('throttle:120,1')->name('api.finance.projects.invoice-time');
            Route::post('/finance/quotes/{quote}/project', [FinanceProjectPlanController::class, 'projectFromQuote'])->whereNumber('quote')->middleware('throttle:120,1')->name('api.finance.quotes.project');
            // Read-only: a pre-cutover invoice's own PDF (and, per its GoBD
            // correction trail, one historical version's own PDF). whereNumber
            // keeps this disambiguated from the finance-v2 uuid-scoped
            // /finance/invoices/{invoice}/... routes at the same path shape.
            Route::get('/finance/invoices/{invoice}/pdf', [FinanceController::class, 'legacyInvoicePdf'])->whereNumber('invoice')->middleware('throttle:3000,1')->name('api.finance.invoices.legacy-pdf');
            Route::post('/finance/categories', [FinanceController::class, 'storeCategory'])->middleware('throttle:600,1')->name('api.finance.categories.store');
            Route::put('/finance/categories/{category}', [FinanceController::class, 'updateCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('api.finance.categories.update');
            Route::delete('/finance/categories/{category}', [FinanceController::class, 'destroyCategory'])->whereNumber('category')->middleware('throttle:600,1')->name('api.finance.categories.destroy');

            Route::post('/finance/transactions', [FinanceController::class, 'storeTransaction'])->middleware('throttle:600,1')->name('api.finance.transactions.store');
            Route::post('/finance/transactions/bulk', [FinanceController::class, 'bulkTransactions'])->middleware('throttle:120,1')->name('api.finance.transactions.bulk');
            Route::put('/finance/transactions/{transaction}', [FinanceController::class, 'updateTransaction'])->whereNumber('transaction')->middleware('throttle:600,1')->name('api.finance.transactions.update');
            Route::delete('/finance/transactions/{transaction}', [FinanceController::class, 'destroyTransaction'])->whereNumber('transaction')->middleware('throttle:600,1')->name('api.finance.transactions.destroy');
            Route::post('/finance/transactions/{id}/restore', [FinanceController::class, 'restoreTransaction'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.transactions.restore');
            Route::delete('/finance/transactions/{id}/force', [FinanceController::class, 'forceDeleteTransaction'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.transactions.force');
            Route::post('/finance/transactions/{transaction}/receipts', [FinanceController::class, 'attachReceipt'])->whereNumber('transaction')->middleware('throttle:1200,1')->name('api.finance.transactions.receipts.store');
            Route::get('/finance/transactions/{transaction}/receipts/{receipt}/raw', [FinanceController::class, 'receiptRaw'])->whereNumber('transaction')->middleware('throttle:3000,1')->name('api.finance.transactions.receipts.raw');
            Route::delete('/finance/transactions/{transaction}/receipts/{receipt}', [FinanceController::class, 'destroyReceipt'])->whereNumber('transaction')->middleware('throttle:600,1')->name('api.finance.transactions.receipts.destroy');
            // Standalone receipts ("Fremdbelege") — a receipt document without a bank transaction.
            Route::post('/finance/receipts', [FinanceController::class, 'storeReceipt'])->middleware('throttle:1200,1')->name('api.finance.receipts.store');
            Route::put('/finance/receipts/{receipt}', [FinanceController::class, 'updateReceipt'])->whereNumber('receipt')->middleware('throttle:600,1')->name('api.finance.receipts.update');
            Route::delete('/finance/receipts/{receipt}', [FinanceController::class, 'destroyStandaloneReceipt'])->whereNumber('receipt')->middleware('throttle:600,1')->name('api.finance.receipts.destroy');
            Route::post('/finance/receipts/{id}/restore', [FinanceController::class, 'restoreStandaloneReceipt'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.receipts.restore');
            Route::delete('/finance/receipts/{id}/force', [FinanceController::class, 'forceDeleteStandaloneReceipt'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.receipts.force');
            Route::get('/finance/receipts/{receipt}/raw', [FinanceController::class, 'receiptFile'])->whereNumber('receipt')->middleware('throttle:3000,1')->name('api.finance.receipts.raw');
        });

        // Per-user Paperless-ngx integration: cached term quick-picks, live term
        // creation, document forwarding, and cache sync. The /documents endpoint is
        // a transient-cleartext boundary (client posts bytes; server forwards to the
        // user's own Paperless and stores/logs nothing).
        Route::get('/paperless/terms', [ApiPaperlessController::class, 'terms'])->middleware('throttle:60,1')->name('api.paperless.terms');
        Route::post('/paperless/terms', [ApiPaperlessController::class, 'createTerm'])->middleware('throttle:30,1')->name('api.paperless.terms.create');
        Route::post('/paperless/documents', [ApiPaperlessController::class, 'submit'])->middleware('throttle:20,1')->name('api.paperless.documents');
        Route::post('/paperless/sync', [ApiPaperlessController::class, 'sync'])->middleware('throttle:20,1')->name('api.paperless.sync');
        // Per-user Paperless connection config (URL + enabled + token). GET/PUT
        // never return the token (has_token bool); PUT preserves a blank token.
        Route::get('/paperless/config', [ApiPaperlessController::class, 'config'])->middleware('throttle:60,1')->name('api.paperless.config');
        Route::put('/paperless/config', [ApiPaperlessController::class, 'updateConfig'])->middleware('throttle:30,1')->name('api.paperless.config.update');
        Route::post('/paperless/config/test', [ApiPaperlessController::class, 'testConfig'])->middleware('throttle:20,1')->name('api.paperless.config.test');

        // Per-user company profile + invoice defaults (non-secret business identity).
        Route::get('/company', [ApiCompanyController::class, 'show'])->name('api.company.show');
        Route::put('/company', [ApiCompanyController::class, 'update'])->middleware('throttle:60,1')->name('api.company.update');
        Route::get('/company/logo', [ApiCompanyController::class, 'logo'])->middleware('throttle:120,1')->name('api.company.logo');

        // Site-icon (BIMI/favicon) proxy: guard-agnostic, SSRF-guarded, nothing
        // stored server-side. Retained for the Finance module (bank logos /
        // partner favicons).
        Route::get('/passwords/icon', [PasswordIconController::class, 'fetch'])->middleware('throttle:120,1')->name('api.passwords.icon');

        // Connected devices: list, revoke a device's token, request a remote wipe of a
        // lost device (the wipe flag is delivered on that device's next heartbeat).
        // Same guard-agnostic controller as the web routes.
        Route::get('/devices', [DevicePairingController::class, 'devices'])->name('api.devices.index');
        Route::delete('/devices/{token}', [DevicePairingController::class, 'revokeDevice'])->middleware('throttle:20,1')->name('api.devices.revoke');
        Route::post('/devices/{token}/wipe', [DevicePairingController::class, 'wipeDevice'])->middleware('throttle:20,1')->name('api.devices.wipe');
        Route::delete('/devices/{token}/push', [DevicePairingController::class, 'revokeDevicePush'])->middleware('throttle:20,1')->name('api.devices.push.revoke');
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
        Route::get('/account/sessions', [AccountController::class, 'sessions'])->name('api.account.sessions.index');
        Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->middleware('throttle:20,1')->name('api.account.sessions.revoke');

        Route::post('/locale', [LocaleController::class, 'update'])->name('api.locale.update');
        Route::post('/theme', [ThemeController::class, 'update'])->name('api.theme.update');
        Route::post('/preferences', [PreferencesController::class, 'update'])->name('api.preferences.update');

        // 2FA management: enable, QR/secret, confirm, recovery codes, regenerate, disable.
        // Mirrors Fortify's web routes (/user/two-factor-*) for Sanctum bearer clients.
        Route::prefix('user')->name('api.user.')->group(function (): void {
            Route::prefix('two-factor')->name('2fa.')->group(function (): void {
                Route::post('/enable', [ApiTwoFactorController::class, 'enable'])->middleware('throttle:10,1')->name('enable');
                Route::get('/qr', [ApiTwoFactorController::class, 'qr'])->middleware('throttle:30,1')->name('qr');
                Route::post('/confirm', [ApiTwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('confirm');
                // POST (not GET): the current_password step-up travels in the JSON
                // body, never the query string — a password in a URL leaks into
                // access/request logs, history and proxies (and OkHttp forbids a GET body).
                Route::post('/recovery-codes', [ApiTwoFactorController::class, 'recoveryCodes'])->middleware('throttle:30,1')->name('recovery-codes');
                Route::post('/recovery-codes/regenerate', [ApiTwoFactorController::class, 'regenerateRecoveryCodes'])->middleware('throttle:10,1')->name('recovery-codes.regenerate');
                Route::delete('/', [ApiTwoFactorController::class, 'disable'])->middleware('throttle:10,1')->name('disable');
            });

            Route::put('/password', [ApiPasswordController::class, 'update'])->middleware('throttle:10,1')->name('password');
            Route::post('/email/verify/resend', [ApiTwoFactorController::class, 'resendVerification'])->middleware('throttle:6,1')->name('email.verify.resend');

            // Passkeys / hardware security keys (owner-scoped; register needs a
            // current-password step-up).
            Route::prefix('passkeys')->name('passkeys.')->group(function (): void {
                Route::get('/', [ApiPasskeyController::class, 'index'])->name('index');
                Route::post('/options', [ApiPasskeyController::class, 'registerOptions'])->middleware('throttle:30,1')->name('options');
                Route::post('/', [ApiPasskeyController::class, 'register'])->middleware('throttle:20,1')->name('register');
                Route::put('/{credential}', [ApiPasskeyController::class, 'rename'])->whereNumber('credential')->middleware('throttle:30,1')->name('rename');
                Route::delete('/{credential}', [ApiPasskeyController::class, 'destroy'])->whereNumber('credential')->middleware('throttle:30,1')->name('destroy');
            });
        });

        // Admin workspace settings (JSON mirrors of the web Settings/* pages).
        // Gated by the admin role on top of the device token. Secret values
        // (SMTP/ntfy/webhook creds, Paperless token) are never serialised.
        Route::middleware('can:manage-global-settings')->prefix('admin')->name('api.admin.')->group(function (): void {
            // Admin overview dashboard (server status, resources, health, counts).
            Route::get('/dashboard', [ApiDashboardController::class, 'show'])->name('dashboard.show');

            // Notifications (SMTP / NTFY / webhook) + test send.
            Route::get('/notifications', [ApiNotificationsController::class, 'show'])->name('notifications.show');
            Route::put('/notifications', [ApiNotificationsController::class, 'update'])->middleware('throttle:60,1')->name('notifications.update');
            Route::post('/notifications/test', [ApiNotificationsController::class, 'test'])->middleware('throttle:20,1')->name('notifications.test');

            // Device policy (paired-device cap).
            Route::get('/security', [ApiSecurityController::class, 'show'])->name('security.show');
            Route::put('/security', [ApiSecurityController::class, 'update'])->middleware('throttle:60,1')->name('security.update');

            // Session/auth lifetimes, retention windows.
            Route::get('/limits', [ApiLimitsController::class, 'show'])->name('limits.show');
            Route::put('/limits', [ApiLimitsController::class, 'update'])->middleware('throttle:60,1')->name('limits.update');

            // Container control (bounded agent). List services + run an allowlisted action.
            Route::get('/docker/containers', [ApiDockerController::class, 'containers'])->name('docker.containers');
            Route::post('/docker/action', [ApiDockerController::class, 'action'])->middleware('throttle:30,1')->name('docker.action');

            // System / maintenance overview (read-only) + resolve an error event.
            Route::get('/system', [ApiSystemController::class, 'show'])->middleware('throttle:60,1')->name('system.show');
            Route::post('/system/errors/{error}/resolve', [ApiSystemController::class, 'resolveError'])->whereNumber('error')->middleware('throttle:60,1')->name('system.errors.resolve');

            // Workspace self-registration toggle (mirrors Settings/UsersController@registration).
            Route::get('/registration', [ApiUsersController::class, 'registrationShow'])->name('registration.show');
            Route::put('/registration', [ApiUsersController::class, 'registration'])->middleware('throttle:60,1')->name('registration.update');

            // Security portal: verbose request log, IP block-list, per-user block,
            // and a cross-user session/device overview. Admin-gated.
            Route::get('/request-log', [SecurityPortalController::class, 'requestLog'])->middleware('throttle:120,1')->name('request-log');
            Route::get('/request-log/export', [SecurityPortalController::class, 'requestLogExport'])->middleware('throttle:10,1')->name('request-log.export');
            Route::get('/blocked-ips', [SecurityPortalController::class, 'blocks'])->name('blocked-ips.index');
            Route::post('/blocked-ips', [SecurityPortalController::class, 'blockIp'])->middleware('throttle:60,1')->name('blocked-ips.store');
            Route::delete('/blocked-ips/{blockedIp}', [SecurityPortalController::class, 'unblockIp'])->whereNumber('blockedIp')->middleware('throttle:60,1')->name('blocked-ips.destroy');
            Route::post('/users/{user}/block', [SecurityPortalController::class, 'blockUser'])->whereNumber('user')->middleware('throttle:60,1')->name('users.block');
            Route::post('/users/{user}/unblock', [SecurityPortalController::class, 'unblockUser'])->whereNumber('user')->middleware('throttle:60,1')->name('users.unblock');
            Route::get('/sessions', [SecurityPortalController::class, 'sessions'])->middleware('throttle:60,1')->name('sessions.index');
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
            Route::post('/runs/{run}/restore', [ApiBackupController::class, 'restoreRun'])->middleware('throttle:10,1')->name('runs.restore');
        });
    });
});
