<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\UpdateTokenIp;
use App\Modules\Finance\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/finance-v2')
    ->name('api.finance-v2.')
    ->middleware([
        'api',
        'auth:sanctum',
        'abilities:device',
        UpdateTokenIp::class,
        EnsureTwoFactorEnrolled::class,
        'module:finance',
        'throttle:120,1',
    ])
    ->group(function (): void {
        Route::get('/health', HealthController::class)->name('health');
    });
