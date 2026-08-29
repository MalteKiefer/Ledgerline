<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\UpdateTokenIp;
use App\Modules\Finance\Http\Controllers\HealthController;
use App\Modules\Finance\Http\Controllers\Invoices\InvoiceRevisionController;
use App\Modules\Finance\Http\Controllers\Quotes\QuoteController;
use App\Modules\Finance\Http\Controllers\Quotes\QuoteDecisionController;
use App\Modules\Finance\Http\Controllers\Quotes\QuoteDeliveryController;
use App\Modules\Finance\Http\Controllers\Quotes\QuoteDraftController;
use App\Modules\Finance\Http\Controllers\Quotes\QuoteDuplicationController;
use App\Modules\Finance\Http\Controllers\Quotes\QuoteInvoiceConversionController;
use App\Modules\Finance\Http\Controllers\Quotes\QuotePublicationController;
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
        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
        Route::post('/quotes/preview', [QuoteController::class, 'preview'])->name('quotes.preview');
        Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::get('/quotes/{quote}', [QuoteController::class, 'show'])->whereUuid('quote')->name('quotes.show');
        Route::get('/quotes/{quote}/revisions', [QuoteController::class, 'revisions'])->whereUuid('quote')->name('quotes.revisions.index');
        Route::put('/quotes/{quote}/draft', [QuoteDraftController::class, 'update'])->whereUuid('quote')->name('quotes.draft.update');
        Route::delete('/quotes/{quote}/draft', [QuoteDraftController::class, 'discard'])->whereUuid('quote')->name('quotes.draft.discard');
        Route::post('/quotes/{quote}/versions', [QuoteDraftController::class, 'startVersion'])->whereUuid('quote')->name('quotes.versions.store');
        Route::post('/quotes/{quote}/publish', [QuotePublicationController::class, 'publish'])->whereUuid('quote')->name('quotes.publish');
        Route::post('/quotes/{quote}/send', [QuoteDeliveryController::class, 'send'])->whereUuid('quote')->name('quotes.send');
        Route::post('/quotes/{quote}/accept', [QuoteDecisionController::class, 'accept'])->whereUuid('quote')->name('quotes.accept');
        Route::post('/quotes/{quote}/decline', [QuoteDecisionController::class, 'decline'])->whereUuid('quote')->name('quotes.decline');
        Route::post('/quotes/{quote}/duplicate', [QuoteDuplicationController::class, 'duplicate'])->whereUuid('quote')->name('quotes.duplicate');
        Route::post('/quotes/{quote}/conversions/invoice', [QuoteInvoiceConversionController::class, 'convert'])->whereUuid('quote')->name('quotes.convert.invoice');
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
