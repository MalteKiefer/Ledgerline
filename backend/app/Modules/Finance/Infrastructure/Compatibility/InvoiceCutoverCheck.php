<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use Illuminate\Support\Facades\DB;

/**
 * The executable cutover gate for the invoice/payment/recurring slice:
 * every owner who has any legacy invoice or a bank-transaction invoice
 * link must have a `complete` migration checkpoint AND exactly matching
 * control totals. Task 17 must not flip the canonical routes until this
 * reports ready for every owner.
 */
final readonly class InvoiceCutoverCheck
{
    public function __construct(private InvoiceControlTotals $totals) {}

    /**
     * @return array{
     *   ready: bool,
     *   owners: list<array{
     *     user_id: int,
     *     checkpoint_status: string,
     *     controls_ok: bool,
     *     mismatches: list<string>,
     *   }>,
     * }
     */
    public function run(): array
    {
        $ownerIds = $this->ownersRequiringMigration();
        $rows = [];
        $ready = true;

        foreach ($ownerIds as $ownerId) {
            $checkpoint = DB::table('finance_invoice_migration_checkpoints')->where('user_id', $ownerId)->first();
            $status = is_object($checkpoint) && is_string($checkpoint->status) ? $checkpoint->status : 'pending';
            $comparison = $this->totals->compare($ownerId);
            $ownerReady = $status === 'complete' && $comparison['ok'];
            $ready = $ready && $ownerReady;

            $rows[] = [
                'user_id' => $ownerId,
                'checkpoint_status' => $status,
                'controls_ok' => $comparison['ok'],
                'mismatches' => $comparison['mismatches'],
            ];
        }

        return ['ready' => $ready, 'owners' => $rows];
    }

    /** @return list<int> */
    private function ownersRequiringMigration(): array
    {
        $fromInvoices = DB::table('invoices')->distinct()->pluck('user_id');
        $fromTransactions = DB::table('bank_transactions')->whereNotNull('invoice_id')->distinct()->pluck('user_id');

        /** @var list<int> $ids */
        $ids = $fromInvoices->merge($fromTransactions)
            ->map(static function (mixed $id): int {
                if (! is_numeric($id)) {
                    throw new \LogicException('Expected a numeric user_id.');
                }

                return (int) $id;
            })
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $ids;
    }
}
