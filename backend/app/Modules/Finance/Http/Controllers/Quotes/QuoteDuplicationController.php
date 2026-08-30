<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Modules\Finance\Application\Commands\Quotes\DuplicateQuote;
use App\Modules\Finance\Application\DTOs\Quotes\DuplicateQuoteData;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Http\Requests\Quotes\QuoteActionRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class QuoteDuplicationController extends QuoteController
{
    public function duplicate(QuoteActionRequest $request, string $quote, DuplicateQuote $command, GetQuote $getQuote): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        try {
            $view = $command->handle(new DuplicateQuoteData(
                $id,
                $request->expectedVersion(),
                $request->sourceRevisionId(),
                $request->idempotencyKey(),
            ));

            return $this->quoteResponse($request, $view, 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }
}
