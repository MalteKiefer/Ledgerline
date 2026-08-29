<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\UpdateTokenIp;
use App\Modules\Finance\Http\Controllers\HealthController;
use App\Modules\Finance\Http\Controllers\Invoices\InvoiceRevisionController;
use App\Modules\Finance\Http\Controllers\Quotes\QuoteRevisionPdfController;
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
        Route::get(
            '/quotes/{quote}/revisions/{revision}/pdf',
            QuoteRevisionPdfController::class,
        )
            ->whereUuid('quote')
            ->whereNumber('revision')
            ->name('quotes.revisions.pdf');
        Route::get(
            '/invoices/{invoice}/revisions/{revision}/pdf',
            InvoiceRevisionController::class,
        )
            ->whereUuid('invoice')
            ->whereNumber('revision')
            ->name('invoices.revisions.pdf');
    });
