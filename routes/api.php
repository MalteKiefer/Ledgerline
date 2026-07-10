<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
 * Mobile API. Versioned under /api/v1; the native app authenticates with a
 * first-party Sanctum bearer obtained via QR device pairing. The data endpoints
 * (added in Phase 3) reuse the web controllers — every payload is opaque
 * ciphertext / a sealed manifest, so the server stays zero-knowledge for the
 * app exactly as it does for the browser.
 */
Route::prefix('v1')->group(function (): void {
    // Public pairing exchange — the one-time code is the credential; hard-throttled.
    Route::middleware('throttle:auth-pair')->group(function (): void {
        Route::post('/auth/pair', [AuthController::class, 'pair'])->name('api.auth.pair');
        Route::get('/auth/pair', [AuthController::class, 'collect'])->name('api.auth.collect');
    });

    // Bearer-authenticated surface.
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.me');
        Route::delete('/auth/session', [AuthController::class, 'destroy'])->name('api.auth.destroy');
        // Phase 3 adds the data endpoints (vault/store/files/gallery) here.
    });
});
