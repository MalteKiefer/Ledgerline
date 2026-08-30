<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\CreateLedgerEntryData;
use App\Modules\Finance\Application\DTOs\Projects\LedgerEntryView;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;

final readonly class CreateLedgerEntry
{
    public function __construct(private ProjectWorkRepository $work, private ProjectReferenceResolver $references) {}

    public function handle(CreateLedgerEntryData $data): LedgerEntryView
    {
        $this->references->assertOwnedCategoryReference($data->projectId->ownerId, $data->categoryReference);
        $this->references->assertOwnedPaymentMethodReference($data->projectId->ownerId, $data->paymentMethodReference);

        return $this->work->createLedgerEntry($data);
    }
}
