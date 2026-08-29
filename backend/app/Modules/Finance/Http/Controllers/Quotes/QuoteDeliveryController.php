<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Modules\Finance\Application\Commands\Quotes\SendQuote;
use App\Modules\Finance\Application\DTOs\Quotes\SendQuoteData;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Http\Requests\Quotes\SendQuoteRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class QuoteDeliveryController extends QuoteController
{
    public function send(SendQuoteRequest $request, string $quote, SendQuote $command, GetQuote $getQuote): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        try {
            $result = $command->handleResult(new SendQuoteData(
                $id,
                $request->expectedVersion(),
                $request->idempotencyKey(),
                $request->recipient(),
                $request->changeReason(),
            ));

            return $this->quoteResponse($request, $result->quote, $result->replayed ? 200 : 202);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }
}
