<?php

declare(strict_types=1);

use App\Http\Controllers\ActiveTeamController;
use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefaultTeamController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FileOverviewController;
use App\Http\Controllers\IncomeEntryController;
use App\Http\Controllers\Invoice\CreditNoteController;
use App\Http\Controllers\Invoice\FinalizeController;
use App\Http\Controllers\Invoice\PaymentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings\CompanyController as SettingsCompanyController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Settings\TagController as SettingsTagController;
use App\Http\Controllers\Settings\TeamController as SettingsTeamController;
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
    Route::post('/active-team', [ActiveTeamController::class, 'update'])->name('active-team.update');
    Route::post('/default-team', [DefaultTeamController::class, 'update'])->name('default-team.update');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');
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
    Route::get('/settings/teams', [SettingsTeamController::class, 'index'])->name('settings.teams.index');
    Route::post('/settings/teams/reassign', [SettingsTeamController::class, 'reassign'])->name('settings.teams.reassign');
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
        Route::resource('expenses', ExpenseController::class);
        Route::post('expenses/{expense}/files', [FileController::class, 'storeForExpense'])->name('expenses.files.store');
        Route::resource('time-entries', TimeEntryController::class)->except('show');
        Route::resource('income-entries', IncomeEntryController::class)->except('show');
        Route::resource('invoices', InvoiceController::class);
        Route::post('invoices/{invoice}/finalize', [FinalizeController::class, 'store'])->name('invoices.finalize');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('invoices.payments.store');
        Route::post('invoices/{invoice}/credit-note', [CreditNoteController::class, 'store'])->name('invoices.credit-note');
    });

    // Files: attached to a customer or project; a team-wide overview; download
    // and delete by id.
    Route::get('/files', FileOverviewController::class)->name('files.index');
    Route::post('/files', [FileController::class, 'store'])->name('files.store');
    Route::post('/customers/{customer}/files', [FileController::class, 'storeForCustomer'])->name('customers.files.store');
    Route::post('/projects/{project}/files', [FileController::class, 'storeForProject'])->name('projects.files.store');
    Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::get('/files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::put('/files/{file}', [FileController::class, 'update'])->name('files.update');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
});
