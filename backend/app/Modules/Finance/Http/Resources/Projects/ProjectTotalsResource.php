<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Resources\Projects;

use App\Modules\Finance\Application\DTOs\Projects\ProjectTotalsView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectTotalsResource extends JsonResource
{
    public function __construct(private readonly ProjectTotalsView $totals)
    {
        parent::__construct($totals);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $currencies = [];
        foreach ($this->totals->currencies as $currency => $values) {
            $currencies[$currency] = [
                'hours_scaled' => (string) $values['hours_scaled'],
                'time_value_minor' => (string) $values['time_value_minor'],
                'ledger_minor' => (string) $values['ledger_minor'],
                'financial_minor' => (string) $values['financial_minor'],
            ];
        }

        return ['project_id' => $this->totals->projectId->uuid, 'currencies' => $currencies];
    }
}
