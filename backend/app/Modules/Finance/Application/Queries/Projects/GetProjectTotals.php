<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectTotalsView;
use App\Modules\Finance\Application\Ports\Projects\ProjectFinancialSource;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;

final readonly class GetProjectTotals
{
    public function __construct(private ProjectWorkRepository $work, private ProjectFinancialSource $financial) {}

    public function handle(ProjectId $projectId): ProjectTotalsView
    {
        $totals = $this->work->localTotals($projectId);
        $rows = $this->financial->rows($projectId->ownerId, $projectId);
        $settled = [];
        foreach ($rows as $row) {
            foreach ($row->settlingTransactionReferences as $ref) {
                $settled[$ref] = true;
            }
        } foreach ($rows as $row) {
            if (isset($settled[$row->sourceReference])) {
                continue;
            } $totals[$row->currency] ??= ['hours_scaled' => 0, 'time_value_minor' => 0, 'ledger_minor' => 0];
            $totals[$row->currency]['financial_minor'] = ($totals[$row->currency]['financial_minor'] ?? 0) + $row->signedMinor;
        } foreach ($totals as &$row) {
            $row['financial_minor'] ??= 0;
        } unset($row);
        ksort($totals);

        return new ProjectTotalsView($projectId, $totals);
    }
}
