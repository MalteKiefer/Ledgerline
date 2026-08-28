<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Services\Quotes\QuoteDraftFactory;

final readonly class CreateQuote
{
    public function __construct(
        private QuoteDraftFactory $factory,
        private QuoteRepository $quotes,
    ) {}

    public function handle(int $ownerId, string $idempotencyKey, QuoteDraftData $data): QuoteView
    {
        $requestSha256 = $this->factory->requestSha256($data);

        return $this->quotes->createDraftIdempotently(
            $ownerId,
            $idempotencyKey,
            $requestSha256,
            fn (): array => $this->factory->build($ownerId, $data),
        );
    }
}
