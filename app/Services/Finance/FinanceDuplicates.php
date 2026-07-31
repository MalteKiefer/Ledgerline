<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\BankTransaction;
use App\Models\Invoice;

/**
 * Read-only duplicate detection over the owner's finance rows. Reports suspect
 * groups only — never deletes or merges anything (the owner decides). Owner-
 * scoped + trashed-excluded via the models' global scopes.
 */
class FinanceDuplicates
{
    /**
     * @return array{invoices: list<array{reason: string, key: string, ids: list<int>}>, transactions: list<array{reason: string, key: string, ids: list<int>}>}
     */
    public function detect(): array
    {
        return [
            'invoices' => $this->invoiceDuplicates(),
            'transactions' => $this->transactionDuplicates(),
        ];
    }

    /** @return list<array{reason: string, key: string, ids: list<int>}> */
    private function invoiceDuplicates(): array
    {
        $byNumber = [];
        $byAmount = [];
        foreach (Invoice::query()->get() as $inv) {
            $number = trim((string) ($inv->number ?? ''));
            // A shared invoice number within the same year is the hard GoBD smell
            // (imported archival numbers may legitimately repeat — but still worth
            // surfacing for the owner to eyeball).
            if ($number !== '') {
                $byNumber[(int) $inv->year.'|'.$number][] = (int) $inv->id;
            }
            $customer = is_array($inv->customer) ? $inv->customer : [];
            $custName = is_string($customer['name'] ?? null) ? $customer['name'] : '';
            $name = mb_strtolower(trim($custName));
            $gross = number_format((float) $inv->gross, 2, '.', '');
            $date = (string) $inv->issue_date?->format('Y-m-d');
            if ($name !== '' && $gross !== '0.00') {
                $byAmount[$date.'|'.$gross.'|'.$name][] = (int) $inv->id;
            }
        }

        return array_merge(
            $this->groupsOf($byNumber, 'same_number'),
            $this->groupsOf($byAmount, 'same_date_amount_customer'),
        );
    }

    /** @return list<array{reason: string, key: string, ids: list<int>}> */
    private function transactionDuplicates(): array
    {
        $byEref = [];
        $bySig = [];
        foreach (BankTransaction::query()->get() as $tx) {
            $eref = trim((string) ($tx->eref ?? ''));
            if ($eref !== '') {
                $byEref[$eref][] = (int) $tx->id;
            }
            $sig = implode('|', [
                (string) $tx->date,
                number_format((float) $tx->amount, 2, '.', ''),
                mb_strtolower(trim((string) ($tx->counterparty ?? ''))),
                mb_strtolower(trim((string) ($tx->purpose ?? ''))),
            ]);
            $bySig[$sig][] = (int) $tx->id;
        }

        return array_merge(
            $this->groupsOf($byEref, 'same_eref'),
            $this->groupsOf($bySig, 'same_date_amount_counterparty_purpose'),
        );
    }

    /**
     * @param  array<string, list<int>>  $map
     * @return list<array{reason: string, key: string, ids: list<int>}>
     */
    private function groupsOf(array $map, string $reason): array
    {
        $out = [];
        foreach ($map as $key => $ids) {
            if (count($ids) > 1) {
                $out[] = ['reason' => $reason, 'key' => $key, 'ids' => array_values(array_unique($ids))];
            }
        }

        return $out;
    }
}
