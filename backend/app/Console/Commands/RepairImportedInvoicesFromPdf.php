<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\FinancePartner;
use App\Models\Invoice;
use App\Services\Invoices\HistoricInvoicePdfParser;
use App\Support\BinaryProcess;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Repairs historic imported invoices from their attached, original PDFs.
 *
 * No document is changed unless its extracted table sums to the persisted net
 * amount to the cent. Existing manual/versioned corrections stay untouched.
 */
class RepairImportedInvoicesFromPdf extends Command
{
    protected $signature = 'finance:repair-imported-invoices {--user= : Owning user ID} {--apply : Persist verified repairs}';

    protected $description = 'Restore historic imported invoice positions and customer snapshots from original PDFs';

    public function __construct(private readonly HistoricInvoicePdfParser $parser)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userId = $this->userId();
        if ($userId === null) {
            $this->error('Pass --user=<positive user ID>.');

            return self::FAILURE;
        }
        if (! BinaryProcess::available('pdftotext')) {
            $this->error('pdftotext is required for historic invoice repair.');

            return self::FAILURE;
        }
        $diskName = config('files.disk');
        if (! is_string($diskName) || config('filesystems.disks.'.$diskName.'.driver') !== 'local') {
            $this->error('Historic invoice repair requires the local files disk.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $partnerUpdates = $this->enrichPartners($userId, $apply);
        $candidates = 0;
        $repaired = 0;
        $skipped = 0;
        foreach (Invoice::withoutGlobalScopes()->where('user_id', $userId)->where('imported', true)->orderBy('id')->get() as $invoice) {
            if ((int) $invoice->version_seq !== 0) {
                continue;
            }
            $proposal = $this->proposal($invoice, $diskName);
            if ($proposal === null) {
                $skipped++;

                continue;
            }
            $candidates++;
            $this->line($invoice->number.': '.$proposal['lineCount'].' verified PDF row(s).');
            if (! $apply) {
                continue;
            }
            DB::transaction(function () use ($invoice, $proposal, &$repaired): void {
                $current = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
                if (! $current->imported || (int) $current->version_seq !== 0) {
                    return;
                }
                $snapshot = [
                    'seq' => 1,
                    'label' => 'Historic PDF import correction '.Carbon::today()->toDateString(),
                    'reason' => 'Line items and customer snapshot reconstructed from the attached original PDF.',
                    'at' => now()->toIso8601String(),
                    'snapshot' => ['customer' => $current->customer, 'lines' => $current->lines, 'version' => $current->version],
                ];
                $current->forceFill([
                    'customer' => $proposal['customer'],
                    'lines' => $proposal['lines'],
                    'versions' => [$snapshot],
                    'version_seq' => 1,
                    'version' => (int) $current->version + 1,
                ])->save();
                AuditLog::record('finance.invoice.pdf_import_corrected', $current, [
                    'source' => 'attached_original_pdf', 'line_count' => $proposal['lineCount'],
                ], (int) $current->user_id);
                $repaired++;
            });
        }

        $this->info(($apply ? 'Repaired ' : 'Would repair ').$candidates.' invoice(s); skipped '.$skipped.' unverified PDF(s); '
            .($apply ? 'enriched ' : 'would enrich ').$partnerUpdates.' partner(s).');
        if ($apply && $repaired !== $candidates) {
            $this->warn('One or more invoices changed while this command was running and were left untouched.');
        }

        return self::SUCCESS;
    }

    /** Fill only missing partner master data from linked, historic invoice snapshots. */
    private function enrichPartners(int $userId, bool $apply): int
    {
        $count = 0;
        foreach (FinancePartner::withoutGlobalScopes()->where('user_id', $userId)->get() as $partner) {
            $sources = Invoice::withoutGlobalScopes()
                ->where('user_id', $userId)->where('imported', true)->where('partner_id', $partner->id)->get(['customer']);
            $patch = [];
            foreach ($sources as $invoice) {
                $customer = is_array($invoice->customer) ? $invoice->customer : [];
                foreach (['email', 'invoiceEmail'] as $field) {
                    $email = $customer[$field] ?? null;
                    if ($partner->email === null && is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $patch['email'] = $email;
                    }
                    if ($partner->invoice_email === null && is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $patch['invoice_email'] = $email;
                    }
                }
                $vatId = $customer['vatId'] ?? null;
                if ($partner->vat_id === null && is_string($vatId) && preg_match('/^[A-Z]{2}[A-Z0-9]{5,20}$/', $vatId) === 1) {
                    $patch['vat_id'] = $vatId;
                }
            }
            if ($patch === []) {
                continue;
            }
            $count++;
            $this->line($partner->name.': '.implode(', ', array_keys($patch)).'.');
            if (! $apply) {
                continue;
            }
            DB::transaction(function () use ($partner, $patch): void {
                $current = FinancePartner::withoutGlobalScopes()->lockForUpdate()->findOrFail($partner->id);
                $fill = [];
                foreach ($patch as $field => $value) {
                    if ($current->getAttribute($field) === null) {
                        $fill[$field] = $value;
                    }
                }
                if ($fill === []) {
                    return;
                }
                $current->forceFill($fill + ['version' => (int) $current->version + 1])->save();
                AuditLog::record('finance.partner.invoice_import_enriched', $current, ['fields' => array_keys($fill)], (int) $current->user_id);
            });
        }

        return $count;
    }

    /** @return array{customer: array<string, mixed>, lines: list<array<string, mixed>>, lineCount: int}|null */
    private function proposal(Invoice $invoice, string $diskName): ?array
    {
        if (! is_string($invoice->pdf_path) || ! str_starts_with($invoice->pdf_path, 'invoices/')) {
            return null;
        }
        $path = Storage::disk($diskName)->path($invoice->pdf_path);
        $text = BinaryProcess::run(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-'], 60);
        $vatRate = is_numeric($invoice->vat_rate) ? (float) $invoice->vat_rate : 0.0;
        $rows = $text === null ? [] : $this->parser->lines($text, $vatRate);
        $net = is_numeric($invoice->net) ? (float) $invoice->net : null;
        // Historic templates disagree on whether their last table column is net
        // or gross. Quantity × unit price is the common, tax-independent net
        // invariant and therefore the only safe cross-template validation.
        $netSum = array_sum(array_map(static fn (array $row): float => $row['qty'] * $row['unitPrice'], $rows));
        if ($rows === [] || $net === null || abs($netSum - $net) > 0.01) {
            return null;
        }

        $partner = $invoice->partner_id === null ? null : FinancePartner::withoutGlobalScopes()->find($invoice->partner_id);
        $customer = $this->customer($invoice, $partner);
        $lines = array_map(static fn (array $row): array => [
            'desc' => $row['desc'], 'qty' => $row['qty'], 'unit' => $row['unit'],
            'unitPrice' => $row['unitPrice'], 'vatRate' => $row['vatRate'],
        ], $rows);

        return ['customer' => $customer, 'lines' => $lines, 'lineCount' => count($lines)];
    }

    /** @return array<string, mixed> */
    private function customer(Invoice $invoice, ?FinancePartner $partner): array
    {
        $current = is_array($invoice->customer) ? $invoice->customer : [];
        if (! $partner instanceof FinancePartner) {
            return $current;
        }
        $contact = is_array($partner->contacts) ? $partner->contacts[0] ?? null : null;
        $attn = is_array($contact) && is_string($contact['name'] ?? null) ? $contact['name'] : null;

        return array_merge($current, [
            'name' => $partner->name,
            'attn' => $attn,
            'address' => $this->addressWithoutContact($partner->address, $attn),
            'email' => $partner->email,
            'invoiceEmail' => $partner->invoice_email ?? $partner->email,
            'vatId' => $partner->vat_id,
            'partnerId' => $partner->id,
        ]);
    }

    private function addressWithoutContact(?string $address, ?string $contact): ?string
    {
        if (! is_string($address) || $address === '') {
            return null;
        }
        $lines = preg_split('/\R/u', $address) ?: [];
        if ($contact !== null && isset($lines[0]) && trim($lines[0]) === $contact) {
            array_shift($lines);
        }

        return trim(implode("\n", $lines)) ?: null;
    }

    private function userId(): ?int
    {
        $userId = $this->option('user');

        return (is_string($userId) || is_int($userId)) && ctype_digit((string) $userId) && (int) $userId > 0
            ? (int) $userId : null;
    }
}
