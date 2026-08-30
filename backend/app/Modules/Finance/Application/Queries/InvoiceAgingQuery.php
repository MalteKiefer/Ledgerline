<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Queries;

use App\Models\UserSetting;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final readonly class InvoiceAgingQuery
{
    /**
     * @return array{
     *   days_1_30:list<array{invoice_id:int,number:string,open_minor:int,currency:string,days_overdue:int}>,
     *   days_31_60:list<array{invoice_id:int,number:string,open_minor:int,currency:string,days_overdue:int}>,
     *   days_61_plus:list<array{invoice_id:int,number:string,open_minor:int,currency:string,days_overdue:int}>,
     *   totals:array{days_1_30_minor:int,days_31_60_minor:int,days_61_plus_minor:int,open_minor:int}
     * }
     */
    public function handle(?DateTimeImmutable $asOf = null): array
    {
        $ownerId = Auth::id();
        if (! is_int($ownerId) || $ownerId < 1) {
            throw new LogicException('Invoice aging requires an authenticated owner.');
        }
        $zone = $this->timezone($ownerId);
        $today = ($asOf ?? new DateTimeImmutable)->setTimezone($zone)->setTime(0, 0);
        $rows = DB::table('finance_invoices as invoices')
            ->join('finance_document_revisions as revisions', function (JoinClause $join): void {
                $join->on('revisions.user_id', '=', 'invoices.user_id')
                    ->on('revisions.document_series_id', '=', 'invoices.document_series_id')
                    ->on('revisions.id', '=', 'invoices.current_revision_id');
            })
            ->where('invoices.user_id', $ownerId)
            ->where('invoices.workflow_status', 'sent')
            ->where('invoices.open_minor', '>', 0)
            ->whereDate('invoices.due_date', '<', $today->format('Y-m-d'))
            ->orderBy('invoices.due_date')
            ->orderBy('invoices.id')
            ->get([
                'invoices.id', 'invoices.number', 'invoices.due_date',
                'invoices.open_minor', 'revisions.currency',
            ]);
        $result = [
            'days_1_30' => [],
            'days_31_60' => [],
            'days_61_plus' => [],
            'totals' => [
                'days_1_30_minor' => 0,
                'days_31_60_minor' => 0,
                'days_61_plus_minor' => 0,
                'open_minor' => 0,
            ],
        ];
        foreach ($rows as $row) {
            if (! is_int($row->id)
                || ! is_string($row->number)
                || ! is_string($row->due_date)
                || ! is_int($row->open_minor)
                || ! is_string($row->currency)) {
                throw new LogicException('Invoice aging encountered an invalid projection.');
            }
            $due = new DateTimeImmutable($row->due_date, $zone);
            $days = (int) $due->setTime(0, 0)->diff($today)->format('%a');
            $bucket = $days <= 30 ? 'days_1_30' : ($days <= 60 ? 'days_31_60' : 'days_61_plus');
            $openMinor = $row->open_minor;
            $result[$bucket][] = [
                'invoice_id' => $row->id,
                'number' => $row->number,
                'open_minor' => $openMinor,
                'currency' => $row->currency,
                'days_overdue' => $days,
            ];
            $result['totals'][$bucket.'_minor'] += $openMinor;
            $result['totals']['open_minor'] += $openMinor;
        }

        return $result;
    }

    public function contains(int $invoiceId, ?DateTimeImmutable $asOf = null): bool
    {
        foreach (['days_1_30', 'days_31_60', 'days_61_plus'] as $bucket) {
            foreach ($this->handle($asOf)[$bucket] as $invoice) {
                if ($invoice['invoice_id'] === $invoiceId) {
                    return true;
                }
            }
        }

        return false;
    }

    private function timezone(int $ownerId): DateTimeZone
    {
        $configured = UserSetting::query()->find($ownerId)?->getAttribute('timezone');
        $fallback = config('app.timezone', 'UTC');
        $name = is_string($configured) && trim($configured) !== ''
            ? trim($configured)
            : (is_string($fallback) ? $fallback : 'UTC');
        try {
            return new DateTimeZone($name);
        } catch (Throwable) {
            return new DateTimeZone(is_string($fallback) ? $fallback : 'UTC');
        }
    }
}
