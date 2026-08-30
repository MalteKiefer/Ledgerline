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
        $sourceReferences = array_fill_keys(array_map(static fn ($row): string => $row->sourceReference, $rows), true);
        foreach ($rows as $row) {
            if (self::hasSettlementSource($row->settlingTransactionReferences, $sourceReferences)) {
                continue;
            }
            $totals[$row->currency] ??= ['hours_scaled' => 0, 'time_value_minor' => 0, 'ledger_minor' => 0];
            $totals[$row->currency]['financial_minor'] = self::checkedAdd($totals[$row->currency]['financial_minor'] ?? 0, $row->signedMinor);
        }
        foreach ($totals as &$row) {
            $row['financial_minor'] ??= 0;
        }
        unset($row);
        ksort($totals);

        return new ProjectTotalsView($projectId, $totals);
    }

    /**
     * @param  list<string>  $settlements
     * @param  array<string, bool>  $sources
     */
    private static function hasSettlementSource(array $settlements, array $sources): bool
    {
        foreach ($settlements as $reference) {
            if (isset($sources[$reference])) {
                return true;
            }
        }

        return false;
    }

    private static function checkedAdd(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right) || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new \DomainException('project_total_overflow');
        }

        return $left + $right;
    }
}
