<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Modules\Finance\Application\Commands\Quotes\AcceptQuote;
use App\Modules\Finance\Application\Commands\Quotes\DeclineQuote;
use App\Modules\Finance\Application\DTOs\Quotes\DecideQuoteData;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Http\Requests\Quotes\QuoteActionRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class QuoteDecisionController extends QuoteController
{
    public function accept(QuoteActionRequest $request, string $quote, AcceptQuote $command, GetQuote $getQuote): JsonResponse
    {
        return $this->decide($request, $quote, $getQuote, $command);
    }

    public function decline(QuoteActionRequest $request, string $quote, DeclineQuote $command, GetQuote $getQuote): JsonResponse
    {
        return $this->decide($request, $quote, $getQuote, $command);
    }

    private function decide(QuoteActionRequest $request, string $uuid, GetQuote $getQuote, AcceptQuote|DeclineQuote $command): JsonResponse
    {
        $id = $this->quoteId($request, $uuid);
        try {
            $view = $command->handle(new DecideQuoteData(
                $id,
                $request->expectedVersion(),
                $request->expectedRevisionId(),
                $request->idempotencyKey(),
            ));

            return $this->quoteResponse($request, $view);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }
}
