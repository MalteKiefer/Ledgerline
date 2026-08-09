<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressBookController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController as ApiBackupController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\ContactsProfileController as ApiContactsProfileController;
use App\Http\Controllers\Api\FilesLimitsController as ApiFilesLimitsController;
use App\Http\Controllers\Api\GroupController as ApiGroupController;
use App\Http\Controllers\Api\InviteLinkController as ApiInviteLinkController;
use App\Http\Controllers\Api\InvoiceOcrController;
use App\Http\Controllers\Api\NotificationsController as ApiNotificationsController;
use App\Http\Controllers\Api\PaperlessController as ApiPaperlessController;
use App\Http\Controllers\Api\PasswordController as ApiPasswordController;
use App\Http\Controllers\Api\SecurityController as ApiSecurityController;
use App\Http\Controllers\Api\SecurityLogController as ApiSecurityLogController;
use App\Http\Controllers\Api\SettingsController as ApiSettingsController;
use App\Http\Controllers\Api\SpaAuthController;
use App\Http\Controllers\Api\SystemController as ApiSystemController;
use App\Http\Controllers\Api\TwoFactorController as ApiTwoFactorController;
use App\Http\Controllers\Api\UsersController as ApiUsersController;
use App\Http\Controllers\Api\WebDavAccessController as ApiWebDavAccessController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CalendarBookController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactDuplicateController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\PublicFileShareController;
use App\Http\Controllers\SharedFolderController;
use App\Http\Controllers\SharedWithMeController;
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

    // Backend-agnostic browser login: email+password (+2FA) → bearer token, so the
    // SPA never depends on a Laravel session cookie (portable to a future Go API).
    Route::post('/auth/login', [SpaAuthController::class, 'login'])->middleware('throttle:10,1')->name('api.auth.login');

    // Public account lifecycle (no auth). Mirrors the web Fortify pipeline via the
    // same actions. forgot-password always answers generically (no enumeration);
    // register is gated by the workspace allow_registration flag (403 when off).
    Route::post('/auth/forgot-password', [SpaAuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('api.auth.forgot-password');
    Route::post('/auth/reset-password', [SpaAuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('api.auth.reset-password');
    Route::post('/auth/register', [SpaAuthController::class, 'register'])->middleware('throttle:6,1')->name('api.auth.register');

    // Public, unauthenticated file-share consumption — the share token in the path is
    // the credential (mounts the SAME guard-agnostic PublicFileShareController as the
    // web routes). A password-gated share issues a stateless HMAC grant on unlock that
    // the tokenless client carries on manifest/raw (X-Share-Grant header or ?grant=).
    Route::prefix('file-share/{token}')->name('api.public.file-share.')->group(function (): void {
        Route::get('/', [PublicFileShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
        Route::post('/unlock', [PublicFileShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
        Route::get('/manifest', [PublicFileShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
        Route::get('/file/{file}/raw', [PublicFileShareController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('file.raw');
    });

    // Public, unauthenticated invite / password-reset link consumption. The admin
    // CREATE side is /api/v1/users/{user}/invite-link; this is the consume side.
    // show reports validity as JSON (never a redirect); store sets the password and
    // mints a bearer (rather than a session login). Hashed single-use expiring token.
    Route::get('/invite/{invite}/{token}', [ApiInviteLinkController::class, 'show'])->middleware('throttle:20,1')->name('api.invite.show');
    Route::post('/invite/{invite}/{token}', [ApiInviteLinkController::class, 'store'])->middleware('throttle:20,1')->name('api.invite.store');

    // Enforce the scoped 'device' ability minted at pairing (legacy '*' tokens
    // still pass) so a token's declared scope is actually checked.
    Route::middleware(['auth:sanctum', 'abilities:device', UpdateTokenIp::class])->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.me');
        Route::post('/auth/logout', [SpaAuthController::class, 'logout'])->name('api.auth.logout');
        // Streams the signed-in user's stored avatar (same-origin, non-secret);
        // 404 when none stored. `me.user.has_avatar` tells the app whether to fetch it.
        Route::get('/avatar', AvatarController::class)->middleware('throttle:120,1')->name('api.avatar');
        Route::post('/avatar', [AvatarController::class, 'store'])->middleware('throttle:30,1')->name('api.avatar.store');
        Route::delete('/avatar', [AvatarController::class, 'destroy'])->middleware('throttle:30,1')->name('api.avatar.destroy');
        Route::post('/device/heartbeat', [AuthController::class, 'heartbeat'])->middleware('throttle:120,1')->name('api.device.heartbeat');
        Route::delete('/auth/session', [AuthController::class, 'destroy'])->name('api.auth.destroy');

        // Transient server-side OCR of a raw receipt: returns line-structured text
        // only (recognition is client-side). Nothing is persisted/logged.
        Route::post('/invoices/ocr', [InvoiceOcrController::class, 'ocr'])->middleware(['throttle:20,1', 'module:finance'])->name('api.invoices.ocr');

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
            Route::post('/finance/invoices/{invoice}/email', [FinanceController::class, 'emailInvoice'])->whereNumber('invoice')->middleware('throttle:10,1')->name('api.finance.invoices.email');
            Route::post('/finance/invoices/{invoice}/storno', [FinanceController::class, 'stornoInvoice'])->whereNumber('invoice')->middleware('throttle:30,1')->name('api.finance.invoices.storno');
            Route::post('/finance/invoices/{invoice}/dun', [FinanceController::class, 'dunInvoice'])->whereNumber('invoice')->middleware('throttle:10,1')->name('api.finance.invoices.dun');
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
            // Standalone receipts ("Fremdbelege") — a receipt document without a bank transaction.
            Route::post('/finance/receipts', [FinanceController::class, 'storeReceipt'])->middleware('throttle:1200,1')->name('api.finance.receipts.store');
            Route::put('/finance/receipts/{receipt}', [FinanceController::class, 'updateReceipt'])->whereNumber('receipt')->middleware('throttle:600,1')->name('api.finance.receipts.update');
            Route::delete('/finance/receipts/{receipt}', [FinanceController::class, 'destroyStandaloneReceipt'])->whereNumber('receipt')->middleware('throttle:600,1')->name('api.finance.receipts.destroy');
            Route::post('/finance/receipts/{id}/restore', [FinanceController::class, 'restoreStandaloneReceipt'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.receipts.restore');
            Route::delete('/finance/receipts/{id}/force', [FinanceController::class, 'forceDeleteStandaloneReceipt'])->whereNumber('id')->middleware('throttle:600,1')->name('api.finance.receipts.force');
            Route::get('/finance/receipts/{receipt}/raw', [FinanceController::class, 'receiptFile'])->whereNumber('receipt')->middleware('throttle:3000,1')->name('api.finance.receipts.raw');
        });

        // Contacts module — mirrors the web routes. The web ContactController
        // methods already return JSON (store/update/destroy/show/data/suggest/
        // geocode/favorite/bulkDestroy/import/export/avatar); mount the same
        // guard-agnostic controllers under /api/v1 so the Vue SPA (and mobile)
        // consume them via device auth. Blade-only methods (index/create/edit/
        // view) are intentionally not exposed. Owner-scope is controller-side.
        Route::middleware('module:contacts')->group(function (): void {
            Route::get('/contacts/data', [ContactController::class, 'data'])->name('api.contacts.data');
            Route::get('/contacts/suggest', [ContactController::class, 'suggest'])->name('api.contacts.suggest');
            Route::get('/contacts/export', [ContactController::class, 'export'])->name('api.contacts.export');
            Route::post('/contacts/import', [ContactController::class, 'import'])->middleware('throttle:60,1')->name('api.contacts.import');
            Route::post('/contacts/settings', [ContactController::class, 'settings'])->middleware('throttle:600,1')->name('api.contacts.settings');
            Route::delete('/contacts/bulk-destroy', [ContactController::class, 'bulkDestroy'])->middleware('throttle:600,1')->name('api.contacts.bulk-destroy');
            Route::post('/contacts', [ContactController::class, 'store'])->middleware('throttle:600,1')->name('api.contacts.store');
            Route::get('/contacts/duplicates/data', [ContactDuplicateController::class, 'data'])->name('api.contacts.duplicates.data');
            Route::post('/contacts/duplicates/merge', [ContactDuplicateController::class, 'merge'])->middleware('throttle:120,1')->name('api.contacts.duplicates.merge');
            Route::post('/contacts/duplicates/dismiss', [ContactDuplicateController::class, 'dismiss'])->middleware('throttle:120,1')->name('api.contacts.duplicates.dismiss');
            Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('api.contacts.show');
            Route::get('/contacts/{contact}/geo', [ContactController::class, 'geocode'])->middleware('throttle:120,1')->name('api.contacts.geo');
            Route::get('/contacts/{contact}/avatar', [ContactController::class, 'avatarImage'])->middleware('throttle:3000,1')->name('api.contacts.avatar');
            Route::patch('/contacts/{contact}/favorite', [ContactController::class, 'favorite'])->middleware('throttle:600,1')->name('api.contacts.favorite');
            Route::post('/contacts/{contact}/avatar', [ContactController::class, 'avatar'])->middleware('throttle:120,1')->name('api.contacts.avatar.upload');
            Route::put('/contacts/{contact}', [ContactController::class, 'update'])->middleware('throttle:600,1')->name('api.contacts.update');
            Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->middleware('throttle:600,1')->name('api.contacts.destroy');
            Route::post('/address-books', [AddressBookController::class, 'store'])->middleware('throttle:600,1')->name('api.address-books.store');
            Route::put('/address-books/{addressBook}', [AddressBookController::class, 'update'])->middleware('throttle:600,1')->name('api.address-books.update');
            Route::delete('/address-books/{addressBook}', [AddressBookController::class, 'destroy'])->middleware('throttle:600,1')->name('api.address-books.destroy');
            Route::post('/contact-groups', [ContactGroupController::class, 'store'])->middleware('throttle:600,1')->name('api.contact-groups.store');
            Route::delete('/contact-groups/{group}', [ContactGroupController::class, 'destroy'])->middleware('throttle:600,1')->name('api.contact-groups.destroy');
            // Downloadable Apple CardDAV enrollment profile (.mobileconfig). Mirrors
            // the web Settings/ContactsController@profile; carries the username, never
            // a password (sync uses the app-specific webdav_password, hashed).
            Route::get('/account/carddav-profile', [ApiContactsProfileController::class, 'carddavProfile'])->middleware('throttle:20,1')->name('api.account.carddav-profile');
        });

        // Calendar module — mirrors the web routes (plaintext-relational calendars
        // + events with recurrence-expanded range query + ICS import/export). The
        // web CalendarController methods already return JSON; mount the same
        // guard-agnostic controllers under /api/v1 so the Vue SPA (and mobile)
        // consume them via device auth. The Blade/SPA entry (index) is not exposed.
        // Owner-scope is controller-side; 409 on etag mismatch.
        Route::middleware('module:calendar')->group(function (): void {
            Route::get('/calendar/data', [CalendarController::class, 'data'])->name('api.calendar.data');
            // OpenHolidays proxies (SSRF-guarded) so the SPA selects load under CSP connect-src 'self'.
            Route::get('/calendar/holiday-countries', [CalendarController::class, 'holidayCountries'])->middleware('throttle:60,1')->name('api.calendar.holiday-countries');
            Route::get('/calendar/holiday-subdivisions', [CalendarController::class, 'holidaySubdivisions'])->middleware('throttle:60,1')->name('api.calendar.holiday-subdivisions');
            Route::get('/calendar/events', [CalendarController::class, 'events'])->name('api.calendar.events');
            Route::get('/calendar/export', [CalendarController::class, 'export'])->name('api.calendar.export');
            Route::post('/calendar/import', [CalendarController::class, 'import'])->middleware('throttle:60,1')->name('api.calendar.import');
            Route::post('/calendar/settings', [CalendarController::class, 'settings'])->middleware('throttle:600,1')->name('api.calendar.settings');
            Route::post('/calendar/events', [CalendarController::class, 'store'])->middleware('throttle:600,1')->name('api.calendar.events.store');
            Route::get('/calendar/events/{event}', [CalendarController::class, 'show'])->name('api.calendar.events.show');
            Route::put('/calendar/events/{event}', [CalendarController::class, 'update'])->middleware('throttle:600,1')->name('api.calendar.events.update');
            Route::delete('/calendar/events/{event}', [CalendarController::class, 'destroy'])->middleware('throttle:600,1')->name('api.calendar.events.destroy');
            Route::post('/calendars', [CalendarBookController::class, 'store'])->middleware('throttle:600,1')->name('api.calendars.store');
            // Special (generated, read-only) calendars: create + (re)generate holidays/birthdays.
            Route::post('/calendars/special', [CalendarController::class, 'storeSpecial'])->middleware('throttle:60,1')->name('api.calendars.special');
            Route::post('/calendars/{calendar}/regenerate', [CalendarController::class, 'regenerate'])->middleware('throttle:60,1')->name('api.calendars.regenerate');
            Route::put('/calendars/{calendar}', [CalendarBookController::class, 'update'])->middleware('throttle:600,1')->name('api.calendars.update');
            Route::delete('/calendars/{calendar}', [CalendarBookController::class, 'destroy'])->middleware('throttle:600,1')->name('api.calendars.destroy');
        });

        // Files module — mirrors the web routes (plaintext-relational folders +
        // files + version history). Gated by module:files on top of device auth.
        Route::middleware('module:files')->group(function (): void {
            Route::get('/files/data', [FilesController::class, 'index'])->name('api.files.index');
            Route::get('/files/trash', [FilesController::class, 'trashed'])->name('api.files.trash');
            Route::get('/files/search', [FileSearchController::class, 'search'])->middleware('throttle:120,1')->name('api.files.search');
            Route::get('/files/labels', [FilesController::class, 'labels'])->name('api.files.labels');
            Route::post('/files/labels', [FilesController::class, 'storeLabel'])->middleware('throttle:600,1')->name('api.files.labels.store');
            Route::put('/files/labels/{label}', [FilesController::class, 'updateLabel'])->whereNumber('label')->middleware('throttle:600,1')->name('api.files.labels.update');
            Route::delete('/files/labels/{label}', [FilesController::class, 'destroyLabel'])->whereNumber('label')->middleware('throttle:600,1')->name('api.files.labels.destroy');
            Route::post('/files/entries/{file}/labels', [FilesController::class, 'setFileLabels'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.entry.labels');
            Route::post('/files/entries', [FilesController::class, 'upload'])->middleware('throttle:1200,1')->name('api.files.upload');
            Route::post('/files/entries/trash/empty', [FilesController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('api.files.empty');
            Route::post('/files/zip', [FilesController::class, 'downloadZip'])->middleware('throttle:120,1')->name('api.files.zip');
            Route::get('/files/stats', [FilesController::class, 'stats'])->middleware('throttle:120,1')->name('api.files.stats');
            Route::put('/files/entries/{file}', [FilesController::class, 'update'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.update');
            Route::delete('/files/entries/{file}', [FilesController::class, 'destroy'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.destroy');
            Route::get('/files/entries/{file}/raw', [FilesController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('api.files.raw');
            Route::get('/files/entries/{file}/thumb', [FilesController::class, 'thumb'])->whereNumber('file')->middleware('throttle:3000,1')->name('api.files.thumb');
            Route::post('/files/entries/{file}/content', [FilesController::class, 'replaceContent'])->whereNumber('file')->middleware('throttle:1200,1')->name('api.files.content');
            Route::post('/files/entries/{file}/toggle', [FilesController::class, 'toggle'])->whereNumber('file')->middleware('throttle:1200,1')->name('api.files.toggle');
            Route::get('/files/entries/{file}/versions', [FilesController::class, 'versions'])->whereNumber('file')->name('api.files.versions');
            Route::get('/files/entries/{file}/versions/{version}/raw', [FilesController::class, 'versionRaw'])->whereNumber(['file', 'version'])->middleware('throttle:3000,1')->name('api.files.version.raw');
            Route::post('/files/entries/{file}/versions/{version}/restore', [FilesController::class, 'restoreVersion'])->whereNumber(['file', 'version'])->middleware('throttle:600,1')->name('api.files.version.restore');
            Route::post('/files/entries/{id}/restore', [FilesController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.files.restore');
            Route::delete('/files/entries/{id}/force', [FilesController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.files.force');
            Route::get('/files/folders', [FilesController::class, 'folders'])->name('api.files.folders');
            Route::post('/files/folders', [FilesController::class, 'storeFolder'])->middleware('throttle:600,1')->name('api.files.folders.store');
            Route::put('/files/folders/{folder}', [FilesController::class, 'renameFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.files.folders.update');
            Route::post('/files/folders/{folder}/move', [FilesController::class, 'moveFolder'])->whereNumber('folder')->middleware('throttle:1200,1')->name('api.files.folders.move');
            Route::delete('/files/folders/{folder}', [FilesController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.files.folders.destroy');
            Route::post('/files/upload/chunk/init', [FilesController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.files.chunk.init');
            Route::post('/files/upload/chunk/part', [FilesController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.files.chunk.part');
            Route::post('/files/upload/chunk/complete', [FilesController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.files.chunk.complete');
            Route::post('/files/upload/chunk/abort', [FilesController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.files.chunk.abort');

            // Sharing: public-link owner side + cross-user folder shares + shared-with-me.
            Route::post('/files/rel-shares', [FilesController::class, 'storeShare'])->middleware('throttle:60,1')->name('api.files.shares.store');
            Route::put('/files/rel-shares/{share}', [FilesController::class, 'updateShare'])->whereNumber('share')->middleware('throttle:60,1')->name('api.files.shares.update');
            Route::delete('/files/rel-shares/{share}', [FilesController::class, 'destroyShare'])->whereNumber('share')->middleware('throttle:60,1')->name('api.files.shares.destroy');
            Route::get('/files/folder-shares', [SharedFolderController::class, 'index'])->name('api.files.folder-shares.index');
            Route::post('/files/folder-shares', [SharedFolderController::class, 'store'])->middleware('throttle:60,1')->name('api.files.folder-shares.store');
            Route::put('/files/folder-shares/{share}/members', [SharedFolderController::class, 'updateMember'])->whereNumber('share')->middleware('throttle:60,1')->name('api.files.folder-shares.members.update');
            Route::delete('/files/folder-shares/{share}/members', [SharedFolderController::class, 'removeMember'])->whereNumber('share')->middleware('throttle:60,1')->name('api.files.folder-shares.members.remove');
            Route::delete('/files/folder-shares/{share}', [SharedFolderController::class, 'destroy'])->whereNumber('share')->middleware('throttle:60,1')->name('api.files.folder-shares.destroy');
            Route::get('/shared-with-me', [SharedWithMeController::class, 'index'])->name('api.shared-with-me.index');
            Route::get('/shared-with-me/{share}', [SharedWithMeController::class, 'browse'])->whereNumber('share')->name('api.shared-with-me.browse');
            Route::get('/shared-with-me/{share}/files/{file}/raw', [SharedWithMeController::class, 'raw'])->whereNumber(['share', 'file'])->middleware('throttle:3000,1')->name('api.shared-with-me.raw');
            Route::post('/shared-with-me/{share}/upload', [SharedWithMeController::class, 'upload'])->whereNumber('share')->middleware('throttle:1200,1')->name('api.shared-with-me.upload');
            Route::put('/shared-with-me/{share}/files/{file}', [SharedWithMeController::class, 'rename'])->whereNumber(['share', 'file'])->middleware('throttle:600,1')->name('api.shared-with-me.rename');
            Route::delete('/shared-with-me/{share}/files/{file}', [SharedWithMeController::class, 'destroy'])->whereNumber(['share', 'file'])->middleware('throttle:600,1')->name('api.shared-with-me.destroy');
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
        Route::delete('/account/sessions/{id}', [AccountController::class, 'revokeSession'])->name('api.account.sessions.revoke');

        // App-specific WebDAV mount password (set/clear); the password is stored
        // hashed and never returned — GET reports enabled + username + mount URL.
        Route::get('/account/webdav', [ApiWebDavAccessController::class, 'show'])->name('api.account.webdav.show');
        Route::put('/account/webdav', [ApiWebDavAccessController::class, 'update'])->middleware('throttle:20,1')->name('api.account.webdav.update');
        Route::delete('/account/webdav', [ApiWebDavAccessController::class, 'destroy'])->middleware('throttle:20,1')->name('api.account.webdav.destroy');

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

        // Admin workspace settings (JSON mirrors of the web Settings/* pages).
        // Gated by the admin role on top of the device token. Secret values
        // (SMTP/ntfy/webhook creds, Paperless token) are never serialised.
        Route::middleware('can:manage-global-settings')->prefix('admin')->name('api.admin.')->group(function (): void {
            // Notifications (SMTP / NTFY / webhook) + test send.
            Route::get('/notifications', [ApiNotificationsController::class, 'show'])->name('notifications.show');
            Route::put('/notifications', [ApiNotificationsController::class, 'update'])->middleware('throttle:60,1')->name('notifications.update');
            Route::post('/notifications/test', [ApiNotificationsController::class, 'test'])->middleware('throttle:20,1')->name('notifications.test');

            // Device policy (paired-device cap).
            Route::get('/security', [ApiSecurityController::class, 'show'])->name('security.show');
            Route::put('/security', [ApiSecurityController::class, 'update'])->middleware('throttle:60,1')->name('security.update');

            // Workspace Files limits (max upload MB + orphan-blob grace hours).
            Route::get('/files-limits', [ApiFilesLimitsController::class, 'show'])->name('files-limits.show');
            Route::put('/files-limits', [ApiFilesLimitsController::class, 'update'])->middleware('throttle:60,1')->name('files-limits.update');

            // System / maintenance overview (read-only) + resolve an error event.
            Route::get('/system', [ApiSystemController::class, 'show'])->middleware('throttle:60,1')->name('system.show');
            Route::post('/system/errors/{error}/resolve', [ApiSystemController::class, 'resolveError'])->whereNumber('error')->middleware('throttle:60,1')->name('system.errors.resolve');

            // Workspace self-registration toggle (mirrors Settings/UsersController@registration).
            Route::get('/registration', [ApiUsersController::class, 'registrationShow'])->name('registration.show');
            Route::put('/registration', [ApiUsersController::class, 'registration'])->middleware('throttle:60,1')->name('registration.update');
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
