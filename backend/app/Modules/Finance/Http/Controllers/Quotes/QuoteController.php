<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Models\User;
use App\Modules\Finance\Application\Commands\Quotes\CreateQuote;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Application\Queries\Quotes\ListQuoteRevisions;
use App\Modules\Finance\Application\Queries\Quotes\ListQuotes;
use App\Modules\Finance\Application\Queries\Quotes\PreviewQuoteTotals;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Domain\Shared\Exception\InvalidMoney;
use App\Modules\Finance\Domain\Shared\Exception\InvalidQuantity;
use App\Modules\Finance\Http\Requests\Quotes\QuoteDraftRequest;
use App\Modules\Finance\Http\Requests\Quotes\QuoteListRequest;
use App\Modules\Finance\Http\Resources\Quotes\QuotePageResource;
use App\Modules\Finance\Http\Resources\Quotes\QuoteResource;
use App\Modules\Finance\Http\Resources\Quotes\QuoteRevisionResource;
use App\Modules\Finance\Http\Resources\Quotes\QuoteWireValues;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class QuoteController
{
    public function index(QuoteListRequest $request, ListQuotes $query): JsonResponse
    {
        $page = $query->handle(
            $request->filters($this->ownerId($request)),
            $request->integer('page', 1),
            $request->integer('per_page', 20),
        );

        return response()->json((new QuotePageResource($page))->resolve($request));
    }

    public function preview(QuoteDraftRequest $request, PreviewQuoteTotals $query): JsonResponse
    {
        try {
            $totals = $query->handle($this->ownerId($request), $request->draft());
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return response()->json(QuoteWireValues::exactIntegerStrings([
            'net_minor' => $totals->netMinor,
            'vat_minor' => $totals->vatMinor,
            'gross_minor' => $totals->grossMinor,
            'discount_minor' => $totals->discountMinor,
            'currency' => $totals->currency,
            'tax_breakdowns' => $totals->taxBreakdowns,
            'issue_date' => $totals->issueDate,
            'valid_until' => $totals->validUntil,
        ]));
    }

    public function store(QuoteDraftRequest $request, CreateQuote $command): JsonResponse
    {
        try {
            $quote = $command->handle($this->ownerId($request), $request->idempotencyKey(), $request->draft());
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->failure($exception);
        }

        return $this->quoteResponse($request, $quote, 201);
    }

    public function show(Request $request, string $quote, GetQuote $query): JsonResponse
    {
        return $this->quoteResponse($request, $query->handle($this->quoteId($request, $quote)));
    }

    public function revisions(Request $request, string $quote, ListQuoteRevisions $query): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        $revisions = array_map(
            static fn ($revision): array => (new QuoteRevisionResource($revision, $id->uuid))->resolve($request),
            $query->handle($id),
        );

        return response()->json($revisions);
    }

    protected function ownerId(Request $request): int
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return (int) $user->id;
    }

    protected function quoteId(Request $request, string $uuid): QuoteId
    {
        return new QuoteId($this->ownerId($request), $uuid);
    }

    protected function quoteResponse(Request $request, QuoteView $quote, int $status = 200): JsonResponse
    {
        return response()->json(
            (new QuoteResource($quote))->resolve($request),
            $status,
            ['ETag' => '"'.$quote->version.'"'],
        );
    }

    protected function actionFailure(
        Request $request,
        DomainException|InvalidArgumentException $exception,
        QuoteId $id,
        GetQuote $getQuote,
    ): JsonResponse {
        $code = $this->errorCode($exception);
        $status = $this->conflictCode($code) ? 409 : 422;
        $payload = ['error' => $code];

        if ($status === 409) {
            $current = $getQuote->handle($id);
            $payload['current'] = (new QuoteResource($current))->resolve($request);

            return response()->json($payload, $status, ['ETag' => '"'.$current->version.'"']);
        }

        return response()->json($payload, $status);
    }

    protected function failure(DomainException|InvalidArgumentException $exception): JsonResponse
    {
        $code = $this->errorCode($exception);

        return response()->json(['error' => $code], $this->conflictCode($code) ? 409 : 422);
    }

    private function conflictCode(string $code): bool
    {
        return in_array($code, [
            'version_conflict',
            'idempotency_key_reused',
            'operation_in_progress',
            'quote_locked',
            'quote_revision_stale',
            'quote_revision_replaced',
            'quote_draft_pending',
            'quote_publication_in_progress',
            'quote_delivery_in_progress',
        ], true);
    }

    private function errorCode(DomainException|InvalidArgumentException $exception): string
    {
        if ($exception instanceof InvalidQuoteAction) {
            return $exception->errorCode;
        }
        if ($exception instanceof DomainException && in_array($exception->getMessage(), [
            'idempotency_key_reused',
            'operation_in_progress',
            'version_conflict',
        ], true)) {
            return $exception->getMessage();
        }
        if ($exception->getMessage() === 'control_totals_mismatch') {
            return 'control_totals_mismatch';
        }
        if ($exception instanceof InvalidMoney) {
            return 'invalid_money';
        }
        if ($exception instanceof InvalidQuantity) {
            return 'invalid_quantity';
        }

        $message = $exception->getMessage();

        return match (true) {
            str_contains($message, 'tax rate') => 'invalid_tax_rate',
            str_contains($message, 'discount') => 'invalid_discount',
            str_contains($message, 'validity'), str_contains($message, 'dates') => 'invalid_validity_period',
            str_contains($message, 'customer') => 'invalid_customer',
            str_contains($message, 'partner') => 'invalid_partner',
            str_contains($message, 'product') => 'invalid_product',
            default => 'invalid_quote_input',
        };
    }
}
