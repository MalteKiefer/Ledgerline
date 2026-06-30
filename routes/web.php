<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\PocketIdController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\SearchController;
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
    Route::get('/profile', ProfileController::class)->name('profile');
    Route::get('/profile/avatar', AvatarController::class)->name('profile.avatar');
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
});
