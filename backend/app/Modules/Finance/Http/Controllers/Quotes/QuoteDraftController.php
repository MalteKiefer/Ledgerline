<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Controllers\Quotes;

use App\Modules\Finance\Application\Commands\Quotes\DiscardQuoteDraft;
use App\Modules\Finance\Application\Commands\Quotes\StartQuoteVersion;
use App\Modules\Finance\Application\Commands\Quotes\UpdateQuoteDraft;
use App\Modules\Finance\Application\Queries\Quotes\GetQuote;
use App\Modules\Finance\Domain\Quotes\Exception\InvalidQuoteAction;
use App\Modules\Finance\Http\Requests\Quotes\QuoteActionRequest;
use App\Modules\Finance\Http\Requests\Quotes\QuoteDraftRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

final class QuoteDraftController extends QuoteController
{
    public function update(QuoteDraftRequest $request, string $quote, UpdateQuoteDraft $command, GetQuote $getQuote): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        try {
            $expectedVersion = $request->expectedVersion();
            $view = $command->handle($id, $expectedVersion, $request->draft());
            if ($view->version !== $expectedVersion + 1) {
                throw new InvalidQuoteAction('version_conflict');
            }

            return $this->quoteResponse($request, $view);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }

    public function discard(QuoteActionRequest $request, string $quote, DiscardQuoteDraft $command, GetQuote $getQuote): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        try {
            $expectedVersion = $request->expectedVersion();
            $view = $command->handle($id, $expectedVersion);
            if ($view->version !== $expectedVersion + 1) {
                throw new InvalidQuoteAction('version_conflict');
            }

            return $this->quoteResponse($request, $view);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }

    public function startVersion(QuoteActionRequest $request, string $quote, StartQuoteVersion $command, GetQuote $getQuote): JsonResponse
    {
        $id = $this->quoteId($request, $quote);
        try {
            $expectedVersion = $request->expectedVersion();
            $view = $command->handle($id, $expectedVersion);
            if ($view->version !== $expectedVersion + 1) {
                throw new InvalidQuoteAction('version_conflict');
            }

            return $this->quoteResponse($request, $view, 201);
        } catch (DomainException|InvalidArgumentException $exception) {
            return $this->actionFailure($request, $exception, $id, $getQuote);
        }
    }
}
