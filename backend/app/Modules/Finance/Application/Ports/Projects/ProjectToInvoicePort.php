<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Ports\Projects;

use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceTimeLine;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;

interface ProjectToInvoicePort
{
    /**
     * @param  list<InvoiceTimeLine>  $lines
     * @param  list<string>  $timeEntryUuids
     */
    public function createDraft(int $ownerId, ProjectView $project, array $lines, array $timeEntryUuids, string $idempotencyKey): InvoiceDraftTarget;
}
