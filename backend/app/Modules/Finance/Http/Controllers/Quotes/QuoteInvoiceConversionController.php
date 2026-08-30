<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Modules\Finance\Application\Commands\Quotes\ConvertQuoteToInvoice;
use App\Modules\Finance\Application\DTOs\Quotes\ConvertQuoteToInvoiceData;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Http\Requests\Quotes\QuoteActionRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class QuoteInvoiceConversionController extends QuoteController
{
    public function convert(QuoteActionRequest $request, string $quote, ConvertQuoteToInvoice $command, GetQuote $getQuote): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        try {
            $target = $command->handle(new ConvertQuoteToInvoiceData(
                $id,
                $request->expectedVersion(),
                $request->expectedRevisionId(),
                $request->idempotencyKey(),
            ));

            return response()->json([
                'target_reference' => $target->targetReference,
                'target_id' => $target->targetId,
            ], 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }
}
