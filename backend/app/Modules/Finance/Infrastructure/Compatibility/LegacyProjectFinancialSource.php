<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\BankTransaction;
use App\Models\FinanceReceipt;
use App\Modules\Finance\Application\DTOs\Projects\ProjectFinancialSourceRow;
use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\Ports\Projects\ProjectFinancialSource;
use App\Modules\Finance\Domain\Shared\Money;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use DateTimeImmutable;

final class LegacyProjectFinancialSource implements ProjectFinancialSource
{
    public function rows(int $ownerId, ProjectId $projectId): array
    {
        $p = ProjectRecord::query()->withoutGlobalScopes()->where('user_id', $ownerId)->where('uuid', $projectId->uuid)->firstOrFail();
        if ($p->source_type !== 'legacy.finance_project') {
            return [];
        }
        $legacy = (int) $p->source_id;
        $rows = [];
        foreach (BankTransaction::query()->withoutGlobalScopes()->where('user_id', $ownerId)->where('finance_project_id', $legacy)->whereNull('deleted_at')->get() as $t) {
            if ($t->date === null) {
                continue;
            }
            $money = Money::fromDecimal((string) $t->amount, (string) $p->currency);
            $rows[] = new ProjectFinancialSourceRow('bank-transaction:'.$t->id, $money->minor(), $money->currency(), new DateTimeImmutable($t->date->format('Y-m-d')));
        }
        foreach (FinanceReceipt::query()->withoutGlobalScopes()->where('user_id', $ownerId)->where('finance_project_id', $legacy)->whereNull('deleted_at')->get() as $r) {
            if ($r->amount === null || $r->currency === null || $r->date === null) {
                continue;
            }
            $money = Money::fromDecimal((string) $r->amount, (string) $r->currency);
            $ids = $r->linked_transaction_ids ?? ($r->bank_transaction_id !== null ? [(int) $r->bank_transaction_id] : []);
            $refs = array_values(array_map(fn ($id) => 'bank-transaction:'.$id, $ids));
            $minor = $money->minor();
            if ($minor === PHP_INT_MIN) {
                throw new \DomainException('project_total_overflow');
            }
            $rows[] = new ProjectFinancialSourceRow('finance-receipt:'.$r->id, -abs($minor), $money->currency(), new DateTimeImmutable($r->date->format('Y-m-d')), $refs);
        }

        return $rows;
    }
}
