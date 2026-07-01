<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileOverviewController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\IncomeEntryController;
use App\Http\Controllers\Invoice\CreditNoteController;
use App\Http\Controllers\Invoice\FinalizeController;
use App\Http\Controllers\Invoice\ImportController as InvoiceImportController;
use App\Http\Controllers\Invoice\MailController as InvoiceMailController;
use App\Http\Controllers\Invoice\PaymentController;
use App\Http\Controllers\Invoice\PdfController as InvoicePdfController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\CompanyController as SettingsCompanyController;
use App\Http\Controllers\Settings\GalleryController as SettingsGalleryController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\TagController as SettingsTagController;
use App\Http\Controllers\Settings\UnitController as SettingsUnitController;
use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

// The root simply forwards to the dashboard; unauthenticated visitors are then
// redirected to the login page by the "auth" middleware.
Route::get('/', static fn () => redirect()->route('dashboard'));

// Guest-only routes: the login page and the Pocket-ID OIDC handshake.
Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');
    Route::get('/auth/redirect', [PocketIdController::class, 'redirect'])->name('auth.redirect');
    Route::get('/auth/callback', [PocketIdController::class, 'callback'])->name('auth.callback');
});

// Authenticated routes.
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
    Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');

    // Settings.
    Route::get('/settings', SettingsController::class)->name('settings');
    Route::get('/settings/company', [SettingsCompanyController::class, 'edit'])->name('settings.company.edit');
    Route::put('/settings/company', [SettingsCompanyController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/company/logo', [SettingsCompanyController::class, 'logo'])->name('settings.company.logo');
    Route::get('/settings/tags', [SettingsTagController::class, 'index'])->name('settings.tags.index');
    Route::post('/settings/tags', [SettingsTagController::class, 'store'])->name('settings.tags.store');
    Route::put('/settings/tags/{tag}', [SettingsTagController::class, 'update'])->name('settings.tags.update');
    Route::delete('/settings/tags/{tag}', [SettingsTagController::class, 'destroy'])->name('settings.tags.destroy');
    Route::get('/settings/gallery', [SettingsGalleryController::class, 'edit'])->name('settings.gallery.edit');
    Route::put('/settings/gallery', [SettingsGalleryController::class, 'update'])->name('settings.gallery.update');
    Route::post('/settings/gallery/rescan', [SettingsGalleryController::class, 'rescan'])->name('settings.gallery.rescan');
    Route::post('/settings/gallery/regenerate', [SettingsGalleryController::class, 'regenerate'])->name('settings.gallery.regenerate');
    Route::post('/settings/gallery/rename', [SettingsGalleryController::class, 'rename'])->name('settings.gallery.rename');
    Route::post('/settings/gallery/run-all', [SettingsGalleryController::class, 'runAll'])->name('settings.gallery.run-all');
    Route::get('/settings/gallery/queue-status', [SettingsGalleryController::class, 'queueStatus'])->name('settings.gallery.queue-status');
    Route::get('/settings/units', [SettingsUnitController::class, 'index'])->name('settings.units.index');
    Route::post('/settings/units', [SettingsUnitController::class, 'store'])->name('settings.units.store');
    Route::put('/settings/units/{unit}', [SettingsUnitController::class, 'update'])->name('settings.units.update');
    Route::delete('/settings/units/{unit}', [SettingsUnitController::class, 'destroy'])->name('settings.units.destroy');
    Route::post('/logout', [PocketIdController::class, 'logout'])->name('logout');

    Route::resource('customers', CustomerController::class);
    Route::resource('customers.contacts', ContactController::class)->shallow();

    // Projects: one unified create/store path (customer chosen in the form),
    // a per-customer listing, and per-record actions. "create" is registered
    // before the {project} routes so it is not captured as a project id.
    Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::resource('customers.projects', ProjectController::class)
        ->shallow()
        ->only(['index', 'show', 'edit', 'update', 'destroy']);

    Route::resource('customers.branches', BranchController::class)->shallow();

    // Customer-independent overview of all projects.
    Route::get('/projects', ProjectOverviewController::class)->name('projects.overview');

    // Finance.
    Route::prefix('finance')->name('finance.')->group(function (): void {
        Route::get('report', FinanceReportController::class)->name('report');
        Route::resource('expenses', ExpenseController::class);
        Route::post('expenses/{expense}/files', [FileController::class, 'storeForExpense'])->name('expenses.files.store');
        Route::resource('time-entries', TimeEntryController::class)->except('show');
        Route::resource('income-entries', IncomeEntryController::class)->except('show');
        Route::get('invoices/trash', [InvoiceController::class, 'trash'])->name('invoices.trash');
        Route::post('invoices/{invoice}/restore', [InvoiceController::class, 'restore'])->name('invoices.restore');
        Route::delete('invoices/{invoice}/force', [InvoiceController::class, 'forceDestroy'])->name('invoices.force-destroy');
        Route::get('invoices/import', [InvoiceImportController::class, 'create'])->name('invoices.import.create');
        Route::post('invoices/import/parse', [InvoiceImportController::class, 'parse'])->name('invoices.import.parse');
        Route::get('invoices/import/next', [InvoiceImportController::class, 'next'])->name('invoices.import.next');
        Route::post('invoices/import/skip', [InvoiceImportController::class, 'skip'])->name('invoices.import.skip');
        Route::post('invoices/import', [InvoiceImportController::class, 'store'])->name('invoices.import.store');
        Route::resource('invoices', InvoiceController::class);
        Route::get('invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');
        Route::post('invoices/{invoice}/email', [InvoiceMailController::class, 'store'])->name('invoices.email');
        Route::post('invoices/{invoice}/finalize', [FinalizeController::class, 'store'])->name('invoices.finalize');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::post('invoices/{invoice}/credit-note', [CreditNoteController::class, 'store'])->name('invoices.credit-note');
    });

    // Gallery: a photo timeline with drag-and-drop upload and a trash.
    Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index');
    Route::post('/gallery', [GalleryController::class, 'store'])->name('gallery.store');
    Route::get('/gallery/feed', [GalleryController::class, 'feed'])->name('gallery.feed');
    Route::get('/gallery/map', [GalleryController::class, 'map'])->name('gallery.map');
    Route::get('/gallery/trips', [GalleryController::class, 'trips'])->name('gallery.trips');
    Route::get('/gallery/points', [GalleryController::class, 'points'])->name('gallery.points');
    Route::get('/gallery/trash', [GalleryController::class, 'trash'])->name('gallery.trash');
    Route::delete('/gallery', [GalleryController::class, 'destroy'])->name('gallery.destroy');
    Route::put('/gallery/{photo}/meta', [GalleryController::class, 'editMeta'])->name('gallery.meta');
    Route::post('/gallery/{photo}/transform', [GalleryController::class, 'transform'])->name('gallery.transform');
    Route::post('/gallery/{photo}/favorite', [GalleryController::class, 'favorite'])->name('gallery.favorite');
    Route::post('/gallery/trash/restore', [GalleryController::class, 'restore'])->name('gallery.restore');
    Route::delete('/gallery/trash', [GalleryController::class, 'forceDestroy'])->name('gallery.force-destroy');
    Route::get('/gallery/{photo}/{size}', [GalleryController::class, 'image'])
        ->whereIn('size', ['thumb', 'medium', 'original'])->name('gallery.image');

    // Files: attached to a customer or project; a team-wide overview; download
    // and delete by id.
    Route::get('/files', FileOverviewController::class)->name('files.index');
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::post('/files/general', [FileController::class, 'storeGeneral'])->name('files.store.general');
    Route::post('/files/bulk/move', [FileController::class, 'bulkMove'])->name('files.bulk.move');
    Route::post('/files/bulk/delete', [FileController::class, 'bulkDelete'])->name('files.bulk.delete');
    Route::put('/files/{file}/rename', [FileController::class, 'rename'])->name('files.rename');

    // Virtual folders for organising files.
    Route::post('/folders', [FolderController::class, 'store'])->name('folders.store');
    Route::put('/folders/{folder}', [FolderController::class, 'update'])->name('folders.update');
    Route::delete('/folders/{folder}', [FolderController::class, 'destroy'])->name('folders.destroy');
    Route::post('/customers/{customer}/files', [FileController::class, 'storeForCustomer'])->name('customers.files.store');
    Route::post('/projects/{project}/files', [FileController::class, 'storeForProject'])->name('projects.files.store');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::put('/files/{file}', [FileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
});
