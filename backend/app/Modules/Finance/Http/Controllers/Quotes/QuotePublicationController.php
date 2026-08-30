<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Modules\Finance\Application\Commands\Quotes\PublishQuote;
use App\Modules\Finance\Application\DTOs\Quotes\PublishQuoteData;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Http\Requests\Quotes\QuoteActionRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class QuotePublicationController extends QuoteController
{
    public function publish(QuoteActionRequest $request, string $quote, PublishQuote $command, GetQuote $getQuote): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        try {
            $view = $command->handle(new PublishQuoteData(
                $id,
                $request->expectedVersion(),
                $request->idempotencyKey(),
                $request->changeReason(),
            ));

            return $this->quoteResponse($request, $view);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }
}
