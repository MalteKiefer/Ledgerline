<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Quotes;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteDraftData;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteTotalsView;
use App\Modules\Finance\Application\Services\Quotes\QuoteDraftFactory;

final readonly class PreviewQuoteTotals
{
    public function __construct(private QuoteDraftFactory $factory) {}

    public function handle(int $ownerId, QuoteDraftData $data): QuoteTotalsView
    {
        return $this->factory->build($ownerId, $data)['preview'];
    }
}
