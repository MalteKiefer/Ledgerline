<?php

declare(strict_types=1);

namespace App\Support\UserData;

use App\Models\BankTransaction;
use App\Models\FinanceReceipt;
use App\Models\Invoice;
use App\Models\User;
use App\Support\BlobStore;

/**
 * Per-user data contributor for the plaintext-relational Finance module. Rows in
 * invoices / bank_transactions / payment_methods / finance_* cascade away with
 * the user delete, but the disk BYTES do not — invoice PDFs (invoices.pdf_path),
 * standalone receipt files (finance_receipts.blob_path) and embedded transaction
 * receipts (bank_transactions.receipts[].blob_path) all live at invoices/<uuid>
 * on the file disk. Without this contributor a GDPR erasure would leave financial
 * PII (customer names, amounts, IBANs) on disk indefinitely, and the data-access
 * export would omit the whole module. Purge deletes every finance blob; export
 * inventories the finance rows.
 */
final class FinanceData implements UserDataContributor
{
    public function key(): string
    {
        return 'finance';
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $uid = $user->getKey();

        return [
            'invoices' => Invoice::query()->withoutGlobalScopes()->withTrashed()
                ->where('user_id', $uid)->orderBy('id')
                ->get(['id', 'number', 'year', 'status', 'customer', 'lines', 'gross', 'net', 'vat', 'issue_date', 'created_at'])
                ->toArray(),
            'transactions' => BankTransaction::query()->withoutGlobalScopes()->withTrashed()
                ->where('user_id', $uid)->orderBy('id')
                ->get(['id', 'payment_method_id', 'date', 'amount', 'counterparty', 'purpose', 'created_at'])
                ->toArray(),
            'receipts' => FinanceReceipt::query()->withoutGlobalScopes()->withTrashed()
                ->where('user_id', $uid)->orderBy('id')
                ->get(['id', 'name', 'amount', 'category', 'note', 'created_at'])
                ->toArray(),
        ];
    }

    public function purge(User $user): void
    {
        $disk = BlobStore::disk();
        $uid = $user->getKey();

        $del = static function (mixed $path) use ($disk): void {
            // All finance blobs share the invoices/ prefix; refuse anything else
            // (defence-in-depth against a tampered path) and skip blanks.
            if (is_string($path) && $path !== '' && str_starts_with($path, 'invoices/') && ! str_contains($path, '..')) {
                $disk->delete($path);
            }
        };

        // Invoice PDFs.
        Invoice::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $uid)
            ->orderBy('id')->chunkById(500, function ($invoices) use ($del): void {
                foreach ($invoices as $inv) {
                    $del($inv->pdf_path);
                    // Per-version PDFs, if any.
                    foreach ((array) ($inv->versions ?? []) as $v) {
                        if (is_array($v)) {
                            $del($v['pdf'] ?? null);
                        }
                    }
                }
            });

        // Standalone receipts (Fremdbelege).
        FinanceReceipt::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $uid)
            ->orderBy('id')->chunkById(500, function ($receipts) use ($del): void {
                foreach ($receipts as $r) {
                    $del($r->blob_path);
                }
            });

        // Receipts embedded on bank transactions (JSON array of {blob_path,...}).
        BankTransaction::query()->withoutGlobalScopes()->withTrashed()->where('user_id', $uid)
            ->orderBy('id')->chunkById(500, function ($txns) use ($del): void {
                foreach ($txns as $tx) {
                    foreach ((array) ($tx->receipts ?? []) as $rec) {
                        if (is_array($rec)) {
                            $del($rec['blob_path'] ?? null);
                        }
                    }
                }
            });
    }
}
