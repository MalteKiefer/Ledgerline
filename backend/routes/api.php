<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AddressBookController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController as ApiBackupController;
use App\Http\Controllers\Api\CalendarProfileController as ApiCalendarProfileController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\ContactsProfileController as ApiContactsProfileController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\DevicePushEndpointController;
use App\Http\Controllers\Api\DockerController as ApiDockerController;
use App\Http\Controllers\Api\FilesLimitsController as ApiFilesLimitsController;
use App\Http\Controllers\Api\GalleryAdminController as ApiGalleryAdminController;
use App\Http\Controllers\Api\GroupController as ApiGroupController;
use App\Http\Controllers\Api\InviteLinkController as ApiInviteLinkController;
use App\Http\Controllers\Api\InvoiceOcrController;
use App\Http\Controllers\Api\LimitsController as ApiLimitsController;
use App\Http\Controllers\Api\MailAccountController;
use App\Http\Controllers\Api\NotificationsController as ApiNotificationsController;
use App\Http\Controllers\Api\PaperlessController as ApiPaperlessController;
use App\Http\Controllers\Api\PasskeyController as ApiPasskeyController;
use App\Http\Controllers\Api\PasswordController as ApiPasswordController;
use App\Http\Controllers\Api\SecurityController as ApiSecurityController;
use App\Http\Controllers\Api\SecurityLogController as ApiSecurityLogController;
use App\Http\Controllers\Api\SecurityPortalController;
use App\Http\Controllers\Api\SettingsController as ApiSettingsController;
use App\Http\Controllers\Api\SpaAuthController;
use App\Http\Controllers\Api\SystemController as ApiSystemController;
use App\Http\Controllers\Api\TwoFactorController as ApiTwoFactorController;
use App\Http\Controllers\Api\UsersController as ApiUsersController;
use App\Http\Controllers\Api\WebDavAccessController as ApiWebDavAccessController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CalendarBookController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CalendarShareController;
use App\Http\Controllers\CalendarTodoController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ContactDuplicateController;
use App\Http\Controllers\ContactGroupController;
use App\Http\Controllers\ContactShareController;
use App\Http\Controllers\CryptoController;
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
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MailAttachmentController;
use App\Http\Controllers\MailBlobController;
use App\Http\Controllers\MailDeleteOriginController;
use App\Http\Controllers\MailExportController;
use App\Http\Controllers\MailFolderController;
use App\Http\Controllers\MailKeyController;
use App\Http\Controllers\MailLabelController;
use App\Http\Controllers\MailLogController;
use App\Http\Controllers\MailMessageController;
use App\Http\Controllers\MailPushbackController;
use App\Http\Controllers\MailRuleController;
use App\Http\Controllers\MailSavedSearchController;
use App\Http\Controllers\MailSeenController;
use App\Http\Controllers\MailSendController;
use App\Http\Controllers\MailStatsController;
use App\Http\Controllers\MailTrashController;
use App\Http\Controllers\MountController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordIconController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\PublicFileShareController;
use App\Http\Controllers\PublicGalleryShareController;
use App\Http\Controllers\PublicGalleryUploadController;
use App\Http\Controllers\ReindexController;
use App\Http\Controllers\SharedFolderController;
use App\Http\Controllers\SharedGalleryController;
use App\Http\Controllers\SharedWithMeController;
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

    // Public, unauthenticated gallery album share consumption (token = credential).
    Route::prefix('gallery-share/{token}')->name('api.public.gallery-share.')->group(function (): void {
        Route::get('/', [PublicGalleryShareController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
        Route::post('/unlock', [PublicGalleryShareController::class, 'unlock'])->middleware('throttle:10,1')->name('unlock');
        Route::get('/manifest', [PublicGalleryShareController::class, 'manifest'])->middleware('throttle:120,1')->name('manifest');
        Route::get('/photo/{photo}/thumb', [PublicGalleryShareController::class, 'thumb'])->whereNumber('photo')->middleware('throttle:6000,1')->name('photo.thumb');
        Route::get('/photo/{photo}/preview', [PublicGalleryShareController::class, 'preview'])->whereNumber('photo')->middleware('throttle:6000,1')->name('photo.preview');
        Route::get('/photo/{photo}/raw', [PublicGalleryShareController::class, 'raw'])->whereNumber('photo')->middleware('throttle:3000,1')->name('photo.raw');
    });

    // Public, unauthenticated gallery album upload links (guest contributions).
    Route::prefix('gallery-upload/{token}')->name('api.public.gallery-upload.')->group(function (): void {
        Route::get('/', [PublicGalleryUploadController::class, 'meta'])->middleware('throttle:120,1')->name('meta');
        Route::post('/', [PublicGalleryUploadController::class, 'store'])->middleware('throttle:30,1')->name('store');
    });

    // Public, unauthenticated inbound upload links: the token in the path is the
    // credential. meta returns the link label + owner; store accepts one file into
    // the owner's folder (owner quota + size cap, hard-throttled). Write-only.
    Route::get('/upload-link/{token}', [FilesController::class, 'uploadLinkMeta'])->middleware('throttle:120,1')->name('api.upload-link.meta');
    Route::post('/upload-link/{token}', [FilesController::class, 'uploadLinkStore'])->middleware('throttle:30,1')->name('api.upload-link.store');

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
        Route::get('/search', [GlobalSearchController::class, 'search'])->middleware('throttle:120,1')->name('api.search');
        Route::post('/me/reindex', [ReindexController::class, 'me'])->middleware('throttle:6,1')->name('api.me.reindex');
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
            Route::get('/finance/number-gaps', [FinanceReportController::class, 'numberGaps'])->middleware('throttle:60,1')->name('api.finance.number-gaps');
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

        // Generic address autocomplete (forward geocode). Device-authenticated but
        // NOT module-gated — both the calendar event editor and the contacts map
        // preview use it. Server-proxied to Nominatim (SSRF-guarded), no-store.
        Route::get('/geo/search', [GeoController::class, 'search'])->middleware('throttle:120,1')->name('api.geo.search');

        // Contacts module — mirrors the web routes. The web ContactController
        // methods already return JSON (store/update/destroy/show/data/suggest/
        // geocode/favorite/bulkDestroy/import/export/avatar); mount the same
        // guard-agnostic controllers under /api/v1 so the Vue SPA (and mobile)
        // consume them via device auth. Blade-only methods (index/create/edit/
        // view) are intentionally not exposed. Owner-scope is controller-side.
        Route::middleware('module:contacts')->group(function (): void {
            Route::get('/contacts/data', [ContactController::class, 'data'])->name('api.contacts.data');
            Route::get('/contacts/shares', [ContactShareController::class, 'index'])->name('api.contacts.shares');
            Route::post('/contacts/shares', [ContactShareController::class, 'store'])->middleware('throttle:60,1')->name('api.contacts.shares.store');
            Route::delete('/contacts/shares/{share}', [ContactShareController::class, 'destroy'])->whereNumber('share')->middleware('throttle:60,1')->name('api.contacts.shares.destroy');
            Route::get('/contacts/shared-with-me', [ContactShareController::class, 'sharedWithMe'])->name('api.contacts.shared.index');
            Route::get('/contacts/shared-with-me/{share}', [ContactShareController::class, 'browse'])->whereNumber('share')->name('api.contacts.shared.browse');
            Route::get('/contacts/birthday-feed', [ContactShareController::class, 'feed'])->name('api.contacts.feed');
            Route::post('/contacts/birthday-feed', [ContactShareController::class, 'enableFeed'])->middleware('throttle:30,1')->name('api.contacts.feed.enable');
            Route::delete('/contacts/birthday-feed', [ContactShareController::class, 'disableFeed'])->middleware('throttle:30,1')->name('api.contacts.feed.disable');
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

        // Notes module — same guard-agnostic controller as web, under device auth.
        Route::middleware('module:notes')->group(function (): void {
            Route::get('/notes/data', [NotesController::class, 'data'])->name('api.notes.data');
            Route::get('/notes/trash', [NotesController::class, 'trash'])->name('api.notes.trash');
            Route::get('/notes/search', [NotesController::class, 'search'])->middleware('throttle:120,1')->name('api.notes.search');
            Route::post('/notes', [NotesController::class, 'store'])->middleware('throttle:600,1')->name('api.notes.store');
            Route::post('/notes/folders', [NotesController::class, 'storeFolder'])->middleware('throttle:600,1')->name('api.notes.folders.store');
            Route::put('/notes/folders/{folder}', [NotesController::class, 'updateFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.notes.folders.update');
            Route::delete('/notes/folders/{folder}', [NotesController::class, 'destroyFolder'])->whereNumber('folder')->middleware('throttle:600,1')->name('api.notes.folders.destroy');
            Route::post('/notes/folders/{id}/restore', [NotesController::class, 'restoreFolder'])->whereNumber('id')->middleware('throttle:600,1')->name('api.notes.folders.restore');
            Route::get('/notes/{note}', [NotesController::class, 'show'])->whereNumber('note')->name('api.notes.show');
            Route::get('/notes/{note}/backlinks', [NotesController::class, 'backlinks'])->whereNumber('note')->name('api.notes.backlinks');
            Route::get('/notes/{note}/export', [NotesController::class, 'export'])->whereNumber('note')->name('api.notes.export');
            Route::post('/notes/{note}/attachments', [NotesController::class, 'attach'])->whereNumber('note')->middleware('throttle:120,1')->name('api.notes.attachments.store');
            Route::post('/notes/{note}/attachments/from', [NotesController::class, 'attachFrom'])->whereNumber('note')->middleware('throttle:120,1')->name('api.notes.attachments.from');
            Route::get('/notes/{note}/attachments/{attachment}/raw', [NotesController::class, 'attachmentRaw'])->whereNumber('note')->whereNumber('attachment')->middleware('throttle:3000,1')->name('api.notes.attachments.raw');
            Route::delete('/notes/{note}/attachments/{attachment}', [NotesController::class, 'destroyAttachment'])->whereNumber('note')->whereNumber('attachment')->middleware('throttle:600,1')->name('api.notes.attachments.destroy');
            Route::put('/notes/{note}', [NotesController::class, 'update'])->whereNumber('note')->middleware('throttle:600,1')->name('api.notes.update');
            Route::patch('/notes/{note}/favorite', [NotesController::class, 'favorite'])->whereNumber('note')->middleware('throttle:600,1')->name('api.notes.favorite');
            Route::patch('/notes/{note}/pin', [NotesController::class, 'pin'])->whereNumber('note')->middleware('throttle:600,1')->name('api.notes.pin');
            Route::delete('/notes/{note}', [NotesController::class, 'destroy'])->whereNumber('note')->middleware('throttle:600,1')->name('api.notes.destroy');
            Route::post('/notes/{id}/restore', [NotesController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.notes.restore');
            Route::delete('/notes/{id}/force', [NotesController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.notes.force');
        });

        // Gallery module — same guard-agnostic controller as web, under device auth.
        Route::middleware('module:gallery')->group(function (): void {
            Route::get('/gallery/memories', [GalleryController::class, 'memories'])->middleware('throttle:120,1')->name('api.gallery.memories');
            Route::get('/gallery/data', [GalleryController::class, 'data'])->name('api.gallery.data');
            Route::get('/gallery/dates', [GalleryController::class, 'dates'])->name('api.gallery.dates');
            Route::get('/gallery/search', [GalleryController::class, 'search'])->middleware('throttle:120,1')->name('api.gallery.search');
            Route::get('/gallery/duplicates', [GalleryController::class, 'duplicates'])->middleware('throttle:60,1')->name('api.gallery.duplicates');
            Route::get('/gallery/people', [GalleryPeopleController::class, 'people'])->name('api.gallery.people');
            Route::post('/gallery/people/merge', [GalleryPeopleController::class, 'merge'])->middleware('throttle:300,1')->name('api.gallery.people.merge');
            Route::get('/gallery/people/{person}', [GalleryPeopleController::class, 'person'])->whereNumber('person')->name('api.gallery.people.show');
            Route::put('/gallery/people/{person}', [GalleryPeopleController::class, 'personUpdate'])->whereNumber('person')->middleware('throttle:600,1')->name('api.gallery.people.update');
            Route::delete('/gallery/people/{person}', [GalleryPeopleController::class, 'personDestroy'])->whereNumber('person')->middleware('throttle:300,1')->name('api.gallery.people.destroy');
            Route::get('/gallery/{photo}/faces', [GalleryPeopleController::class, 'photoFaces'])->whereNumber('photo')->withTrashed()->name('api.gallery.photo.faces');
            Route::get('/gallery/faces/{face}/crop', [GalleryPeopleController::class, 'faceCrop'])->whereNumber('face')->middleware('throttle:6000,1')->name('api.gallery.faces.crop');
            Route::post('/gallery/faces/{face}/assign', [GalleryPeopleController::class, 'faceAssign'])->whereNumber('face')->middleware('throttle:300,1')->name('api.gallery.faces.assign');
            Route::post('/gallery/faces/{face}/hide', [GalleryPeopleController::class, 'faceHide'])->whereNumber('face')->middleware('throttle:300,1')->name('api.gallery.faces.hide');
            Route::get('/gallery/contacts/{contact}/photos', [GalleryPeopleController::class, 'contactPhotos'])->name('api.gallery.contact.photos');
            Route::post('/gallery/reprocess', [GalleryController::class, 'reprocess'])->middleware('throttle:60,1')->name('api.gallery.reprocess');
            Route::get('/gallery/ml-status', [GalleryController::class, 'mlStatus'])->name('api.gallery.ml-status');
            // Sharing — owner side
            Route::get('/gallery/shares', [GalleryShareController::class, 'index'])->name('api.gallery.shares');
            Route::post('/gallery/shares/public', [GalleryShareController::class, 'storePublic'])->middleware('throttle:60,1')->name('api.gallery.shares.public.store');
            Route::put('/gallery/shares/public/{share}', [GalleryShareController::class, 'updatePublic'])->whereNumber('share')->middleware('throttle:60,1')->name('api.gallery.shares.public.update');
            Route::delete('/gallery/shares/public/{share}', [GalleryShareController::class, 'destroyPublic'])->whereNumber('share')->middleware('throttle:60,1')->name('api.gallery.shares.public.destroy');
            Route::post('/gallery/shares/internal', [GalleryShareController::class, 'storeInternal'])->middleware('throttle:60,1')->name('api.gallery.shares.internal.store');
            Route::delete('/gallery/shares/internal/{share}', [GalleryShareController::class, 'destroyInternal'])->whereNumber('share')->middleware('throttle:60,1')->name('api.gallery.shares.internal.destroy');
            Route::get('/gallery/{photo}/comments', [GalleryCommentController::class, 'index'])->whereNumber('photo')->middleware('throttle:600,1')->name('api.gallery.comments.index');
            Route::post('/gallery/{photo}/comments', [GalleryCommentController::class, 'store'])->whereNumber('photo')->middleware('throttle:120,1')->name('api.gallery.comments.store');
            Route::delete('/gallery/comments/{comment}', [GalleryCommentController::class, 'destroy'])->whereNumber('comment')->middleware('throttle:120,1')->name('api.gallery.comments.destroy');
            Route::post('/gallery/{photo}/react', [GalleryCommentController::class, 'react'])->whereNumber('photo')->middleware('throttle:300,1')->name('api.gallery.react');
            Route::post('/gallery/upload-links', [GalleryShareController::class, 'storeUploadLink'])->middleware('throttle:30,1')->name('api.gallery.upload-links.store');
            Route::delete('/gallery/upload-links/{link}', [GalleryShareController::class, 'destroyUploadLink'])->whereNumber('link')->middleware('throttle:30,1')->name('api.gallery.upload-links.destroy');
            // Sharing — recipient side
            Route::get('/gallery/shared-with-me', [SharedGalleryController::class, 'index'])->name('api.gallery.shared.index');
            Route::get('/gallery/shared-with-me/{share}', [SharedGalleryController::class, 'browse'])->whereNumber('share')->name('api.gallery.shared.browse');
            Route::get('/gallery/shared-with-me/{share}/photo/{photo}/thumb', [SharedGalleryController::class, 'thumb'])->whereNumber('share')->whereNumber('photo')->middleware('throttle:6000,1')->name('api.gallery.shared.thumb');
            Route::get('/gallery/shared-with-me/{share}/photo/{photo}/preview', [SharedGalleryController::class, 'preview'])->whereNumber('share')->whereNumber('photo')->middleware('throttle:6000,1')->name('api.gallery.shared.preview');
            Route::get('/gallery/shared-with-me/{share}/photo/{photo}/raw', [SharedGalleryController::class, 'raw'])->whereNumber('share')->whereNumber('photo')->middleware('throttle:3000,1')->name('api.gallery.shared.raw');
            Route::post('/gallery/shared-with-me/{share}/upload', [SharedGalleryController::class, 'upload'])->whereNumber('share')->middleware('throttle:1200,1')->name('api.gallery.shared.upload');
            Route::get('/gallery/trash', [GalleryController::class, 'trash'])->name('api.gallery.trash');
            Route::post('/gallery', [GalleryController::class, 'upload'])->middleware('throttle:1200,1')->name('api.gallery.upload');
            Route::post('/gallery/chunk/init', [GalleryController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.gallery.chunk.init');
            Route::post('/gallery/chunk/part', [GalleryController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.gallery.chunk.part');
            Route::post('/gallery/chunk/complete', [GalleryController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.gallery.chunk.complete');
            Route::post('/gallery/chunk/abort', [GalleryController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.gallery.chunk.abort');
            Route::get('/gallery/{photo}/raw', [GalleryController::class, 'raw'])->whereNumber('photo')->withTrashed()->middleware('throttle:3000,1')->name('api.gallery.raw');
            Route::get('/gallery/{photo}/thumb', [GalleryController::class, 'thumb'])->whereNumber('photo')->withTrashed()->middleware('throttle:6000,1')->name('api.gallery.thumb');
            Route::get('/gallery/{photo}/preview', [GalleryController::class, 'preview'])->whereNumber('photo')->withTrashed()->middleware('throttle:6000,1')->name('api.gallery.preview');
            Route::get('/gallery/{photo}/exif', [GalleryController::class, 'exif'])->whereNumber('photo')->middleware('throttle:600,1')->withTrashed()->name('api.gallery.exif');
            Route::patch('/gallery/{photo}/favorite', [GalleryController::class, 'favorite'])->whereNumber('photo')->middleware('throttle:600,1')->name('api.gallery.favorite');
            Route::patch('/gallery/{photo}/archive', [GalleryController::class, 'archive'])->whereNumber('photo')->middleware('throttle:600,1')->name('api.gallery.archive');
            Route::post('/gallery/bulk-archive', [GalleryController::class, 'bulkArchive'])->middleware('throttle:600,1')->name('api.gallery.bulk-archive');
            Route::put('/gallery/{photo}', [GalleryController::class, 'update'])->whereNumber('photo')->middleware('throttle:600,1')->name('api.gallery.update');
            Route::get('/gallery/{photo}/download', [GalleryController::class, 'download'])->whereNumber('photo')->withTrashed()->middleware('throttle:1200,1')->name('api.gallery.download');
            Route::get('/gallery/{photo}/play', [GalleryController::class, 'play'])->whereNumber('photo')->withTrashed()->middleware('throttle:3000,1')->name('api.gallery.play');
            Route::get('/gallery/{photo}/motion', [GalleryController::class, 'motion'])->whereNumber('photo')->withTrashed()->middleware('throttle:3000,1')->name('api.gallery.motion');
            Route::post('/gallery/{photo}/motion', [GalleryController::class, 'attachMotion'])->whereNumber('photo')->middleware('throttle:1200,1')->name('api.gallery.motion.attach');
            Route::delete('/gallery/{photo}', [GalleryController::class, 'destroy'])->whereNumber('photo')->middleware('throttle:600,1')->name('api.gallery.destroy');
            Route::post('/gallery/{id}/restore', [GalleryController::class, 'restore'])->whereNumber('id')->middleware('throttle:600,1')->name('api.gallery.restore');
            Route::delete('/gallery/{id}/force', [GalleryController::class, 'forceDelete'])->whereNumber('id')->middleware('throttle:600,1')->name('api.gallery.force');
            Route::post('/gallery/trash/empty', [GalleryController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('api.gallery.empty');
            Route::post('/gallery/bulk-destroy', [GalleryController::class, 'bulkDestroy'])->middleware('throttle:600,1')->name('api.gallery.bulk-destroy');
            Route::get('/gallery/albums', [GalleryController::class, 'albums'])->name('api.gallery.albums');
            Route::post('/gallery/albums', [GalleryController::class, 'albumStore'])->middleware('throttle:120,1')->name('api.gallery.albums.store');
            Route::put('/gallery/albums/{album}', [GalleryController::class, 'albumUpdate'])->whereNumber('album')->middleware('throttle:120,1')->name('api.gallery.albums.update');
            Route::delete('/gallery/albums/{album}', [GalleryController::class, 'albumDestroy'])->whereNumber('album')->middleware('throttle:120,1')->name('api.gallery.albums.destroy');
            Route::post('/gallery/albums/{album}/photos', [GalleryController::class, 'albumAttach'])->whereNumber('album')->middleware('throttle:600,1')->name('api.gallery.albums.attach');
            Route::delete('/gallery/albums/{album}/photos', [GalleryController::class, 'albumDetach'])->whereNumber('album')->middleware('throttle:600,1')->name('api.gallery.albums.detach');
        });

        // Calendar module — mirrors the web routes (plaintext-relational calendars
        // + events with recurrence-expanded range query + ICS import/export). The
        // web CalendarController methods already return JSON; mount the same
        // guard-agnostic controllers under /api/v1 so the Vue SPA (and mobile)
        // consume them via device auth. The Blade/SPA entry (index) is not exposed.
        // Owner-scope is controller-side; 409 on etag mismatch.
        Route::middleware('module:calendar')->group(function (): void {
            // Standalone CalDAV enrollment profile (.mobileconfig) — works without the
            // contacts module (the combined CardDAV+CalDAV profile lives on contacts).
            Route::get('/account/caldav-profile', [ApiCalendarProfileController::class, 'caldavProfile'])->middleware('throttle:20,1')->name('api.account.caldav-profile');
            Route::get('/calendar/data', [CalendarController::class, 'data'])->name('api.calendar.data');
            // OpenHolidays proxies (SSRF-guarded) so the SPA selects load under CSP connect-src 'self'.
            Route::get('/calendar/holiday-countries', [CalendarController::class, 'holidayCountries'])->middleware('throttle:60,1')->name('api.calendar.holiday-countries');
            Route::get('/calendar/holiday-subdivisions', [CalendarController::class, 'holidaySubdivisions'])->middleware('throttle:60,1')->name('api.calendar.holiday-subdivisions');
            Route::get('/calendar/events', [CalendarController::class, 'events'])->name('api.calendar.events');
            Route::get('/calendar/export', [CalendarController::class, 'export'])->name('api.calendar.export');
            Route::post('/calendar/import', [CalendarController::class, 'import'])->middleware('throttle:60,1')->name('api.calendar.import');
            Route::post('/calendar/settings', [CalendarController::class, 'settings'])->middleware('throttle:600,1')->name('api.calendar.settings');
            Route::post('/calendar/events/{event}/rsvp', [CalendarController::class, 'rsvp'])->whereUuid('event')->middleware('throttle:120,1')->name('api.calendar.rsvp');
            Route::post('/calendar/imip', [CalendarController::class, 'imipIngest'])->middleware('throttle:60,1')->name('api.calendar.imip');
            Route::get('/calendar/free-busy', [CalendarController::class, 'freeBusy'])->middleware('throttle:600,1')->name('api.calendar.free-busy');
            Route::post('/calendar/slots', [CalendarController::class, 'slots'])->middleware('throttle:120,1')->name('api.calendar.slots');
            Route::get('/calendar/shares', [CalendarShareController::class, 'index'])->middleware('throttle:600,1')->name('api.calendar.shares.index');
            Route::post('/calendar/shares', [CalendarShareController::class, 'store'])->middleware('throttle:60,1')->name('api.calendar.shares.store');
            Route::delete('/calendar/shares/{share}', [CalendarShareController::class, 'destroy'])->whereNumber('share')->middleware('throttle:60,1')->name('api.calendar.shares.destroy');
            Route::post('/calendar/events', [CalendarController::class, 'store'])->middleware('throttle:600,1')->name('api.calendar.events.store');
            Route::get('/calendar/events/{event}', [CalendarController::class, 'show'])->name('api.calendar.events.show');
            Route::put('/calendar/events/{event}', [CalendarController::class, 'update'])->middleware('throttle:600,1')->name('api.calendar.events.update');
            Route::delete('/calendar/events/{event}', [CalendarController::class, 'destroy'])->middleware('throttle:600,1')->name('api.calendar.events.destroy');
            Route::post('/calendar/events/{event}/exclude', [CalendarController::class, 'excludeOccurrence'])->middleware('throttle:600,1')->name('api.calendar.events.exclude');
            Route::put('/calendar/events/{event}/occurrence', [CalendarController::class, 'overrideOccurrence'])->middleware('throttle:600,1')->name('api.calendar.events.occurrence');
            // Tasks (VTODO). Static routes before /calendar/todos/{todo} model binding.
            Route::get('/calendar/todos', [CalendarTodoController::class, 'index'])->name('api.calendar.todos');
            Route::get('/calendar/todos/export', [CalendarTodoController::class, 'export'])->name('api.calendar.todos.export');
            Route::post('/calendar/todos/import', [CalendarTodoController::class, 'import'])->middleware('throttle:60,1')->name('api.calendar.todos.import');
            Route::post('/calendar/todos/reorder', [CalendarTodoController::class, 'reorder'])->middleware('throttle:600,1')->name('api.calendar.todos.reorder');
            Route::post('/calendar/todos', [CalendarTodoController::class, 'store'])->middleware('throttle:600,1')->name('api.calendar.todos.store');
            Route::get('/calendar/todos/{todo}', [CalendarTodoController::class, 'show'])->name('api.calendar.todos.show');
            Route::put('/calendar/todos/{todo}', [CalendarTodoController::class, 'update'])->middleware('throttle:600,1')->name('api.calendar.todos.update');
            Route::delete('/calendar/todos/{todo}', [CalendarTodoController::class, 'destroy'])->middleware('throttle:600,1')->name('api.calendar.todos.destroy');
            Route::post('/calendar/todos/{todo}/complete', [CalendarTodoController::class, 'complete'])->middleware('throttle:600,1')->name('api.calendar.todos.complete');
            Route::post('/calendar/todos/{todo}/uncomplete', [CalendarTodoController::class, 'uncomplete'])->middleware('throttle:600,1')->name('api.calendar.todos.uncomplete');
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
            Route::get('/files/activity', [FilesController::class, 'activity'])->middleware('throttle:600,1')->name('api.files.activity');
            Route::get('/files/entries/{file}/activity', [FilesController::class, 'fileActivity'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.entries.activity');
            Route::get('/files/entries/{file}/info', [FilesController::class, 'info'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.entries.info');
            Route::get('/files/entries/{file}/show', [FilesController::class, 'showEntry'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.entries.show');
            Route::get('/files/search', [FileSearchController::class, 'search'])->middleware('throttle:120,1')->name('api.files.search');
            Route::get('/files/labels', [FilesController::class, 'labels'])->name('api.files.labels');
            Route::post('/files/labels', [FilesController::class, 'storeLabel'])->middleware('throttle:600,1')->name('api.files.labels.store');
            Route::put('/files/labels/{label}', [FilesController::class, 'updateLabel'])->whereNumber('label')->middleware('throttle:600,1')->name('api.files.labels.update');
            Route::delete('/files/labels/{label}', [FilesController::class, 'destroyLabel'])->whereNumber('label')->middleware('throttle:600,1')->name('api.files.labels.destroy');
            Route::post('/files/entries/{file}/labels', [FilesController::class, 'setFileLabels'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.entry.labels');
            Route::post('/files/entries', [FilesController::class, 'upload'])->middleware('throttle:1200,1')->name('api.files.upload');
            Route::post('/files/entries/trash/empty', [FilesController::class, 'emptyTrash'])->middleware('throttle:60,1')->name('api.files.empty');
            Route::post('/files/zip', [FilesController::class, 'downloadZip'])->middleware('throttle:120,1')->name('api.files.zip');
            Route::post('/files/archive', [FilesController::class, 'createArchive'])->middleware('throttle:60,1')->name('api.files.archive');
            Route::post('/files/entries/{file}/extract', [FilesController::class, 'extractArchive'])->whereNumber('file')->middleware('throttle:60,1')->name('api.files.extract');
            Route::post('/files/entries/{file}/encrypt', [FilesController::class, 'encryptEntry'])->whereNumber('file')->middleware('throttle:60,1')->name('api.files.encrypt');
            Route::post('/files/entries/{file}/decrypt', [FilesController::class, 'decryptEntry'])->whereNumber('file')->middleware('throttle:60,1')->name('api.files.decrypt');
            Route::post('/files/folders/{folder}/encrypt', [FilesController::class, 'encryptFolder'])->whereNumber('folder')->middleware('throttle:30,1')->name('api.files.folders.encrypt');
            Route::get('/files/stats', [FilesController::class, 'stats'])->middleware('throttle:120,1')->name('api.files.stats');
            Route::get('/mounts', [MountController::class, 'index'])->name('api.mounts.index');
            Route::post('/mounts', [MountController::class, 'store'])->middleware('throttle:30,1')->name('api.mounts.store');
            Route::post('/mounts/test', [MountController::class, 'test'])->middleware('throttle:30,1')->name('api.mounts.test');
            Route::put('/mounts/{mount}', [MountController::class, 'update'])->whereNumber('mount')->middleware('throttle:30,1')->name('api.mounts.update');
            Route::delete('/mounts/{mount}', [MountController::class, 'destroy'])->whereNumber('mount')->middleware('throttle:30,1')->name('api.mounts.destroy');
            Route::get('/mounts/{mount}/list', [MountController::class, 'list'])->whereNumber('mount')->middleware('throttle:600,1')->name('api.mounts.list');
            Route::get('/mounts/{mount}/file', [MountController::class, 'download'])->whereNumber('mount')->middleware('throttle:600,1')->name('api.mounts.download');
            Route::post('/mounts/{mount}/upload', [MountController::class, 'upload'])->whereNumber('mount')->middleware('throttle:600,1')->name('api.mounts.upload');
            Route::post('/mounts/{mount}/mkdir', [MountController::class, 'mkdir'])->whereNumber('mount')->middleware('throttle:120,1')->name('api.mounts.mkdir');
            Route::post('/mounts/{mount}/delete', [MountController::class, 'deletePath'])->whereNumber('mount')->middleware('throttle:120,1')->name('api.mounts.delete-path');
            Route::put('/files/entries/{file}', [FilesController::class, 'update'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.update');
            Route::delete('/files/entries/{file}', [FilesController::class, 'destroy'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.destroy');
            Route::get('/files/entries/{file}/raw', [FilesController::class, 'raw'])->whereNumber('file')->middleware('throttle:3000,1')->name('api.files.raw');
            Route::get('/files/entries/{file}/thumb', [FilesController::class, 'thumb'])->whereNumber('file')->middleware('throttle:3000,1')->name('api.files.thumb');
            Route::post('/files/entries/{file}/content', [FilesController::class, 'replaceContent'])->whereNumber('file')->middleware('throttle:1200,1')->name('api.files.content');
            Route::post('/files/entries/{file}/toggle', [FilesController::class, 'toggle'])->whereNumber('file')->middleware('throttle:1200,1')->name('api.files.toggle');
            Route::post('/files/entries/{file}/copy', [FilesController::class, 'copy'])->whereNumber('file')->middleware('throttle:600,1')->name('api.files.copy');
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
            Route::post('/files/folders/{id}/restore', [FilesController::class, 'restoreFolder'])->whereNumber('id')->middleware('throttle:600,1')->name('api.files.folders.restore');
            Route::delete('/files/folders/{id}/force', [FilesController::class, 'forceDeleteFolder'])->whereNumber('id')->middleware('throttle:600,1')->name('api.files.folders.force');
            Route::post('/files/upload/chunk/init', [FilesController::class, 'chunkInit'])->middleware('throttle:600,1')->name('api.files.chunk.init');
            Route::post('/files/upload/chunk/part', [FilesController::class, 'chunkPart'])->middleware('throttle:6000,1')->name('api.files.chunk.part');
            Route::post('/files/upload/chunk/complete', [FilesController::class, 'chunkComplete'])->middleware('throttle:600,1')->name('api.files.chunk.complete');
            Route::post('/files/upload/chunk/abort', [FilesController::class, 'chunkAbort'])->middleware('throttle:600,1')->name('api.files.chunk.abort');

            // Sharing: public-link owner side + cross-user folder shares + shared-with-me.
            Route::get('/files/upload-links', [FilesController::class, 'uploadLinks'])->name('api.files.upload-links.index');
            Route::post('/files/upload-links', [FilesController::class, 'storeUploadLink'])->middleware('throttle:60,1')->name('api.files.upload-links.store');
            Route::delete('/files/upload-links/{link}', [FilesController::class, 'destroyUploadLink'])->whereNumber('link')->middleware('throttle:60,1')->name('api.files.upload-links.destroy');
            Route::get('/files/rel-shares', [FilesController::class, 'shares'])->name('api.files.shares.index');
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

        // Mail archive (Phase 1) — plaintext-relational IMAP account config + banned-token-ok: pre-existing mail milestone label, unrelated to this change
        // pull-only sync + the archived-message ledger/reader. Owner-scoped;
        // gated by module:mail on top of device auth. Immutable archive: only
        // seen/trash toggles mutate; raw .eml served sandboxed.
        Route::middleware('module:mail')->group(function (): void {
            Route::get('/mail/accounts', [MailAccountController::class, 'index'])->name('api.mail.accounts.index');
            Route::post('/mail/accounts', [MailAccountController::class, 'store'])->middleware('throttle:60,1')->name('api.mail.accounts.store');
            Route::put('/mail/accounts/{account}', [MailAccountController::class, 'update'])->whereNumber('account')->middleware('throttle:60,1')->name('api.mail.accounts.update');
            Route::delete('/mail/accounts/{account}', [MailAccountController::class, 'destroy'])->whereNumber('account')->middleware('throttle:60,1')->name('api.mail.accounts.destroy');
            Route::post('/mail/accounts/{account}/sync', [MailAccountController::class, 'sync'])->whereNumber('account')->middleware('throttle:60,1')->name('api.mail.accounts.sync');
            Route::post('/mail/accounts/{account}/sync/cancel', [MailAccountController::class, 'cancelSync'])->whereNumber('account')->middleware('throttle:60,1')->name('api.mail.accounts.sync-cancel');
            Route::post('/mail/accounts/{account}/test', [MailAccountController::class, 'test'])->whereNumber('account')->middleware('throttle:6,1')->name('api.mail.accounts.test');
            Route::get('/mail/accounts/{account}/status', [MailAccountController::class, 'status'])->whereNumber('account')->name('api.mail.accounts.status');
            Route::get('/mail/accounts/{account}/logs', [MailLogController::class, 'index'])->whereNumber('account')->middleware('throttle:600,1')->name('api.mail.accounts.logs');
            Route::get('/mail/folders', [MailFolderController::class, 'index'])->middleware('throttle:600,1')->name('api.mail.folders.index');
            Route::get('/mail/messages', [MailMessageController::class, 'index'])->middleware('throttle:1200,1')->name('api.mail.messages.index');
            Route::get('/mail/messages/{message}', [MailMessageController::class, 'show'])->whereUuid('message')->middleware('throttle:1200,1')->name('api.mail.messages.show');
            Route::get('/mail/messages/{message}/body', [MailMessageController::class, 'body'])->whereUuid('message')->middleware('throttle:3000,1')->name('api.mail.messages.body');
            Route::post('/mail/messages/seen', [MailSeenController::class, 'update'])->middleware('throttle:120,1')->name('api.mail.messages.seen');
            Route::post('/mail/messages/trash', [MailTrashController::class, 'trash'])->middleware('throttle:60,1')->name('api.mail.messages.trash');
            Route::post('/mail/messages/restore', [MailTrashController::class, 'restore'])->middleware('throttle:60,1')->name('api.mail.messages.restore');
            Route::post('/mail/messages/labels', [MailLabelController::class, 'apply'])->middleware('throttle:120,1')->name('api.mail.messages.labels');
            Route::get('/mail/labels', [MailLabelController::class, 'index'])->name('api.mail.labels.index');
            Route::post('/mail/labels', [MailLabelController::class, 'store'])->middleware('throttle:60,1')->name('api.mail.labels.store');
            Route::put('/mail/labels/{label}', [MailLabelController::class, 'update'])->whereNumber('label')->middleware('throttle:60,1')->name('api.mail.labels.update');
            Route::delete('/mail/labels/{label}', [MailLabelController::class, 'destroy'])->whereNumber('label')->middleware('throttle:60,1')->name('api.mail.labels.destroy');
            Route::get('/mail/rules', [MailRuleController::class, 'index'])->name('api.mail.rules.index');
            Route::post('/mail/rules', [MailRuleController::class, 'store'])->middleware('throttle:60,1')->name('api.mail.rules.store');
            Route::put('/mail/rules/{rule}', [MailRuleController::class, 'update'])->whereNumber('rule')->middleware('throttle:60,1')->name('api.mail.rules.update');
            Route::delete('/mail/rules/{rule}', [MailRuleController::class, 'destroy'])->whereNumber('rule')->middleware('throttle:60,1')->name('api.mail.rules.destroy');
            Route::get('/mail/saved-searches', [MailSavedSearchController::class, 'index'])->name('api.mail.saved-searches.index');
            Route::post('/mail/saved-searches', [MailSavedSearchController::class, 'store'])->middleware('throttle:60,1')->name('api.mail.saved-searches.store');
            Route::delete('/mail/saved-searches/{search}', [MailSavedSearchController::class, 'destroy'])->whereNumber('search')->middleware('throttle:60,1')->name('api.mail.saved-searches.destroy');
            Route::post('/mail/export', [MailExportController::class, 'export'])->middleware('throttle:30,1')->name('api.mail.export');
            Route::get('/mail/stats', [MailStatsController::class, 'index'])->middleware('throttle:120,1')->name('api.mail.stats');
            Route::post('/mail/messages/{message}/pushback', MailPushbackController::class)->whereUuid('message')->middleware('throttle:30,1')->name('api.mail.messages.pushback');
            Route::post('/mail/messages/{message}/delete-origin', MailDeleteOriginController::class)->whereUuid('message')->middleware('throttle:30,1')->name('api.mail.messages.delete-origin');
            Route::post('/mail/messages/compose', [MailSendController::class, 'compose'])->middleware('throttle:30,1')->name('api.mail.messages.compose');
            Route::post('/mail/messages/{message}/reply', [MailSendController::class, 'reply'])->whereUuid('message')->middleware('throttle:30,1')->name('api.mail.messages.reply');
            Route::post('/mail/messages/{message}/forward', [MailSendController::class, 'forward'])->whereUuid('message')->middleware('throttle:30,1')->name('api.mail.messages.forward');
            Route::get('/mail/attachments/{attachment}/raw', [MailAttachmentController::class, 'raw'])->whereUuid('attachment')->middleware('throttle:3000,1')->name('api.mail.attachments.raw');
            Route::post('/mail/attachments/{attachment}/save', [MailAttachmentController::class, 'save'])->whereUuid('attachment')->middleware('throttle:60,1')->name('api.mail.attachments.save');
            Route::get('/mail/keys', [MailKeyController::class, 'index'])->name('api.mail.keys.index');
            Route::post('/mail/keys', [MailKeyController::class, 'store'])->middleware('throttle:60,1')->name('api.mail.keys.store');
            Route::post('/mail/keys/generate', [MailKeyController::class, 'generate'])->middleware('throttle:30,1')->name('api.mail.keys.generate');
            Route::delete('/mail/keys/{key}', [MailKeyController::class, 'destroy'])->whereNumber('key')->middleware('throttle:60,1')->name('api.mail.keys.destroy');
            Route::get('/mail/raw/{blob}', [MailBlobController::class, 'raw'])->whereUuid('blob')->middleware('throttle:600,1')->name('api.mail.raw');
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

        // App-specific WebDAV mount password (set/clear); the password is stored
        // hashed and never returned — GET reports enabled + username + mount URL.
        Route::get('/account/webdav', [ApiWebDavAccessController::class, 'show'])->name('api.account.webdav.show');
        Route::put('/account/webdav', [ApiWebDavAccessController::class, 'update'])->middleware('throttle:20,1')->name('api.account.webdav.update');
        Route::delete('/account/webdav', [ApiWebDavAccessController::class, 'destroy'])->middleware('throttle:20,1')->name('api.account.webdav.destroy');

        Route::post('/locale', [LocaleController::class, 'update'])->name('api.locale.update');
        Route::post('/theme', [ThemeController::class, 'update'])->name('api.theme.update');
        Route::post('/preferences', [PreferencesController::class, 'update'])->name('api.preferences.update');

        // Shared encryption keyring (profile-level): own keys via the mail key
        // controller mounted here, plus recipients + the encrypt-to picker.
        Route::get('/crypto/keys', [MailKeyController::class, 'index'])->name('api.crypto.keys.index');
        Route::post('/crypto/keys', [MailKeyController::class, 'store'])->middleware('throttle:60,1')->name('api.crypto.keys.store');
        Route::post('/crypto/keys/generate', [MailKeyController::class, 'generate'])->middleware('throttle:30,1')->name('api.crypto.keys.generate');
        Route::delete('/crypto/keys/{key}', [MailKeyController::class, 'destroy'])->whereNumber('key')->middleware('throttle:60,1')->name('api.crypto.keys.destroy');
        Route::get('/crypto/keyring', [CryptoController::class, 'keyring'])->name('api.crypto.keyring');
        Route::post('/crypto/recipients', [CryptoController::class, 'storeRecipient'])->middleware('throttle:60,1')->name('api.crypto.recipients.store');
        Route::delete('/crypto/recipients/{recipient}', [CryptoController::class, 'destroyRecipient'])->whereNumber('recipient')->middleware('throttle:60,1')->name('api.crypto.recipients.destroy');
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
            // Content reindex for ALL users (file text/OCR + gallery photo OCR), queued.
            Route::post('/reindex', [ReindexController::class, 'all'])->middleware('throttle:3,1')->name('reindex');
            // Admin overview dashboard (server status, resources, health, counts).
            Route::get('/dashboard', [ApiDashboardController::class, 'show'])->name('dashboard.show');

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

            // Session/auth lifetimes, retention windows, Files quota.
            Route::get('/limits', [ApiLimitsController::class, 'show'])->name('limits.show');
            Route::put('/limits', [ApiLimitsController::class, 'update'])->middleware('throttle:60,1')->name('limits.update');

            // Gallery & ML: feature flags, models, thresholds, worker queue + rescan.
            Route::get('/gallery', [ApiGalleryAdminController::class, 'show'])->name('gallery.show');
            Route::put('/gallery', [ApiGalleryAdminController::class, 'update'])->middleware('throttle:60,1')->name('gallery.update');
            Route::post('/gallery/queue/clear', [ApiGalleryAdminController::class, 'clearQueue'])->middleware('throttle:20,1')->name('gallery.queue.clear');
            Route::post('/gallery/queue/retry', [ApiGalleryAdminController::class, 'retryFailed'])->middleware('throttle:20,1')->name('gallery.queue.retry');
            Route::post('/gallery/queue/flush', [ApiGalleryAdminController::class, 'flushFailed'])->middleware('throttle:20,1')->name('gallery.queue.flush');
            Route::post('/gallery/reprocess', [ApiGalleryAdminController::class, 'reprocess'])->middleware('throttle:60,1')->name('gallery.reprocess');

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
