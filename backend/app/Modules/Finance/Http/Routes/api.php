<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureTwoFactorEnrolled;
use App\Http\Middleware\UpdateTokenIp;
use App\Modules\Finance\Http\Controllers\Documents\DocumentNoteController;
use App\Modules\Finance\Http\Controllers\HealthController;
use App\Modules\Finance\Http\Controllers\Invoices\InvoiceRevisionController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectActivityController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectArchiveController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectDocumentController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectLedgerController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectMoveController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectNoteController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectStatusController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectTimeInvoiceController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectTotalsController;
use App\Modules\Finance\Http\Controllers\Projects\ProjectWorkController;
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
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereUuid('project')->name('projects.show');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->whereUuid('project')->name('projects.update');
        Route::post('/projects/{project}/status', ProjectStatusController::class)->whereUuid('project')->name('projects.status');
        Route::post('/projects/{project}/move', ProjectMoveController::class)->whereUuid('project')->name('projects.move');
        Route::delete('/projects/{project}', [ProjectArchiveController::class, 'archive'])->whereUuid('project')->name('projects.archive');
        Route::post('/projects/{project}/restore', [ProjectArchiveController::class, 'restore'])->whereUuid('project')->name('projects.restore');
        Route::get('/projects/{project}/work-items', [ProjectWorkController::class, 'workItems'])->whereUuid('project')->name('projects.work-items.index');
        Route::post('/projects/{project}/work-items', [ProjectWorkController::class, 'storeWorkItem'])->whereUuid('project')->name('projects.work-items.store');
        Route::put('/projects/{project}/work-items/{workItem}', [ProjectWorkController::class, 'updateWorkItem'])->whereUuid('project')->whereUuid('workItem')->name('projects.work-items.update');
        Route::delete('/projects/{project}/work-items/{workItem}', [ProjectWorkController::class, 'deleteWorkItem'])->whereUuid('project')->whereUuid('workItem')->name('projects.work-items.destroy');
        Route::post('/projects/{project}/work-items/reorder', [ProjectWorkController::class, 'reorderWorkItems'])->whereUuid('project')->name('projects.work-items.reorder');
        Route::get('/projects/{project}/time-entries', [ProjectWorkController::class, 'timeEntries'])->whereUuid('project')->name('projects.time-entries.index');
        Route::post('/projects/{project}/time-entries', [ProjectWorkController::class, 'storeTime'])->whereUuid('project')->name('projects.time-entries.store');
        Route::put('/projects/{project}/time-entries/{entry}', [ProjectWorkController::class, 'updateTime'])->whereUuid('project')->whereUuid('entry')->name('projects.time-entries.update');
        Route::delete('/projects/{project}/time-entries/{entry}', [ProjectWorkController::class, 'deleteTime'])->whereUuid('project')->whereUuid('entry')->name('projects.time-entries.destroy');
        Route::post('/projects/{project}/invoice-drafts', ProjectTimeInvoiceController::class)->whereUuid('project')->name('projects.invoice-drafts.store');
        Route::get('/projects/{project}/totals', ProjectTotalsController::class)->whereUuid('project')->name('projects.totals.show');
        Route::get('/projects/{project}/ledger', [ProjectLedgerController::class, 'list'])->whereUuid('project')->name('projects.ledger.index');
        Route::post('/projects/{project}/ledger', [ProjectLedgerController::class, 'storeLedger'])->whereUuid('project')->name('projects.ledger.store');
        Route::put('/projects/{project}/ledger/{entry}', [ProjectLedgerController::class, 'updateLedger'])->whereUuid('project')->whereUuid('entry')->name('projects.ledger.update');
        Route::delete('/projects/{project}/ledger/{entry}', [ProjectLedgerController::class, 'deleteLedger'])->whereUuid('project')->whereUuid('entry')->name('projects.ledger.destroy');
        Route::get('/projects/{project}/documents', [ProjectDocumentController::class, 'documents'])->whereUuid('project')->name('projects.documents.index');
        Route::post('/projects/{project}/documents', [ProjectDocumentController::class, 'attach'])->whereUuid('project')->name('projects.documents.store');
        Route::delete('/projects/{project}/documents/{link}', [ProjectDocumentController::class, 'detach'])->whereUuid('project')->whereNumber('link')->name('projects.documents.destroy');
        Route::get('/projects/{project}/document-sources', [ProjectDocumentController::class, 'sources'])->whereUuid('project')->name('projects.document-sources.index');
        Route::get('/projects/{project}/notes', [ProjectNoteController::class, 'notes'])->whereUuid('project')->name('projects.notes.index');
        Route::post('/projects/{project}/notes', [ProjectNoteController::class, 'append'])->whereUuid('project')->name('projects.notes.store');
        Route::get('/projects/{project}/activities', ProjectActivityController::class)->whereUuid('project')->name('projects.activities.index');
        Route::get('/document-series/{series}/notes', [DocumentNoteController::class, 'index'])->whereUuid('series')->name('document-series.notes.index');
        Route::post('/document-series/{series}/notes', [DocumentNoteController::class, 'store'])->whereUuid('series')->name('document-series.notes.store');
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
