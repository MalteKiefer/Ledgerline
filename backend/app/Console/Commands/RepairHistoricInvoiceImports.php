<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Repairs two verified, historically malformed PDF imports.
 *
 * This is deliberately narrow and guarded: it accepts one explicit owner, only
 * touches the two documented invoice numbers while their known broken values are
 * still present, preserves those values in the GoBD version trail and writes an
 * append-only audit record. Run without --apply first.
 */
class RepairHistoricInvoiceImports extends Command
{
    protected $signature = 'finance:repair-historic-imports {--user= : Owning user ID} {--apply : Persist the verified corrections}';

    protected $description = 'Repair the verified historic imports of invoices 2025-3 and 2025-6';

    public function handle(): int
    {
        $userId = $this->option('user');
        if ((! is_string($userId) && ! is_int($userId)) || ! ctype_digit((string) $userId) || (int) $userId < 1) {
            $this->error('Pass --user=<positive user ID>.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $count = 0;
        foreach ($this->corrections() as $number => $correction) {
            $invoice = Invoice::withoutGlobalScopes()
                ->where('user_id', (int) $userId)
                ->where('number', $number)
                ->first();
            if (! $invoice instanceof Invoice) {
                $this->warn($number.': not found; skipped.');

                continue;
            }
            if (! $this->isExpectedBrokenImport($invoice, $correction['expectedGross'], $correction['expectedVatRate'])) {
                $this->warn($number.': no longer matches the verified broken import; skipped.');

                continue;
            }

            if (! $apply) {
                $this->line($number.': would correct gross '.$invoice->gross.' EUR → '.$correction['gross'].' EUR.');
                $count++;

                continue;
            }

            DB::transaction(function () use ($invoice, $correction, $number): void {
                $current = Invoice::withoutGlobalScopes()->lockForUpdate()->findOrFail($invoice->id);
                if (! $this->isExpectedBrokenImport($current, $correction['expectedGross'], $correction['expectedVatRate'])) {
                    throw new \RuntimeException($number.' changed since the dry run.');
                }

                $snapshot = [
                    'seq' => 1,
                    'label' => 'Import correction '.Carbon::today()->toDateString(),
                    'reason' => 'Historic PDF import fields corrected from the original invoice.',
                    'at' => now()->toIso8601String(),
                    'snapshot' => [
                        'net' => $current->net,
                        'vat' => $current->vat,
                        'gross' => $current->gross,
                        'vat_rate' => $current->vat_rate,
                        'customer' => $current->customer,
                        'lines' => $current->lines,
                        'version' => $current->version,
                    ],
                ];
                $current->forceFill([
                    'net' => $correction['net'],
                    'vat' => $correction['vat'],
                    'gross' => $correction['gross'],
                    'vat_rate' => 19,
                    'customer' => $correction['customer'],
                    'lines' => $correction['lines'],
                    'versions' => [$snapshot],
                    'version_seq' => 1,
                    'version' => (int) $current->version + 1,
                ])->save();
                AuditLog::record('finance.invoice.import_corrected', $current, [
                    'source' => 'original_pdf', 'number' => $number, 'gross' => $correction['gross'],
                ], (int) $current->user_id);
            });
            $this->info($number.': corrected.');
            $count++;
        }

        $this->info(($apply ? 'Corrected ' : 'Would correct ').$count.' invoice(s).');

        return self::SUCCESS;
    }

    /** @return array<string, array{expectedGross: float, expectedVatRate: float, net: float, vat: float, gross: float, customer: array<string, mixed>, lines: list<array<string, mixed>>}> */
    private function corrections(): array
    {
        return [
            '2025-3' => [
                'expectedGross' => 19.0, 'expectedVatRate' => 28.0, 'net' => 77.71, 'vat' => 14.78, 'gross' => 92.49,
                'customer' => ['name' => 'Hausmeister Service Töws', 'attn' => 'Vitali Töws', 'address' => "Hochfeldring 85\n76549 Hügelsheim", 'email' => null, 'invoiceEmail' => null, 'vatId' => null, 'contactId' => null, 'partnerId' => 2, 'lang' => 'de', 'footer' => null],
                'lines' => [
                    ['desc' => 'Patchpanel 8-Port Cat 6', 'qty' => 1, 'unit' => 'Stk.', 'unitPrice' => 15.96, 'vatRate' => 19],
                    ['desc' => 'Netzwerkkabel Cat 7 5m', 'qty' => 1, 'unit' => 'Stk.', 'unitPrice' => 7.56, 'vatRate' => 19],
                    ['desc' => 'Netzwerkkabel Cat 7 3m', 'qty' => 1, 'unit' => 'Stk.', 'unitPrice' => 6.72, 'vatRate' => 19],
                    ['desc' => 'TP-Link TL-SG105E 5-Ports Gigabit Switch', 'qty' => 2, 'unit' => 'Stk.', 'unitPrice' => 13.45, 'vatRate' => 19],
                    ['desc' => 'Netzwerkkabel Cat 7 1m', 'qty' => 2, 'unit' => 'Stk.', 'unitPrice' => 4.20, 'vatRate' => 19],
                    ['desc' => 'Netzwerkkabel Cat 7 0,25m', 'qty' => 9, 'unit' => 'Stk.', 'unitPrice' => 1.352222222, 'vatRate' => 19],
                ],
            ],
            '2025-6' => [
                'expectedGross' => 19.0, 'expectedVatRate' => 0.0, 'net' => 145.60, 'vat' => 27.66, 'gross' => 173.26,
                'customer' => ['name' => 'IntellyTec GmbH', 'attn' => 'Ingo Radermacher', 'address' => "Grünenborn 1\n53797 Lohmar", 'email' => null, 'invoiceEmail' => null, 'vatId' => 'DE347517386', 'contactId' => null, 'partnerId' => 1, 'lang' => 'de', 'footer' => null],
                'lines' => [
                    ['desc' => 'Aktualisierung des LF-Share Servers; Beheben von Probleme mit aktualisierten Ubuntu Repositories für den LF Share Server; Aktualisierung des VMServers; Aktualisierung der Windows Server SRV-LF-SU-20, SRV-LF-SU-21, SRV-LF-SU-22, SRV-LF-SU-70, SRV-LF-SU-80, IT-LF-SU-Admin, demo.lemmer-fullwood.software', 'qty' => 1.17, 'unit' => 'h', 'unitPrice' => 40, 'vatRate' => 19],
                    ['desc' => 'Aktualisierung der LFDS1', 'qty' => 0.5, 'unit' => 'h', 'unitPrice' => 40, 'vatRate' => 19],
                    ['desc' => 'Aktualisierung einiger Richtlinien in NinjaOne; Anpassen der Patchoptionen in NinjaOne', 'qty' => 0.57, 'unit' => 'h', 'unitPrice' => 40, 'vatRate' => 19],
                    ['desc' => 'VMServer von LF aktualisiert und neugestartet; Netzkonfiguration kontrolliert und alle Dienste kontrolliert', 'qty' => 0.12, 'unit' => 'h', 'unitPrice' => 0, 'vatRate' => 19],
                    ['desc' => 'Aktualisierung der Server KIR-SWAS003, KIR-SWAS004, KIR-SWDB001, KIR-SWPS001, KIR-SWDC002, KIR-SWDC004, SRV-KN-LB-BUE-1', 'qty' => 1.4, 'unit' => 'h', 'unitPrice' => 40, 'vatRate' => 19],
                ],
            ],
        ];
    }

    /** @param array{expectedGross: float, expectedVatRate: float} $correction */
    private function isExpectedBrokenImport(Invoice $invoice, float $expectedGross, float $expectedVatRate): bool
    {
        return $invoice->imported
            && (int) $invoice->version === 0
            && (int) $invoice->version_seq === 0
            && abs((float) $invoice->gross - $expectedGross) < 0.001
            && abs((float) $invoice->vat_rate - $expectedVatRate) < 0.001;
    }
}
