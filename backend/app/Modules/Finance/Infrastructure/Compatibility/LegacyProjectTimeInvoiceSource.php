<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\FinancePartner;
use App\Modules\Finance\Application\Commands\Invoices\CreateInvoiceDraftFromSource;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceTimeLine;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\Ports\Projects\ProjectToInvoicePort;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Shared\Discount;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Bridges project time billing to the finance-v2 invoice module: it builds a
 * genuine `InvoiceDraftSource` (`sourceType='project_time_batch'`) from the
 * grouped time lines the project module already computed and calls the same
 * `CreateInvoiceDraftFromSource` pipeline that quote conversion and recurring
 * runs use, instead of writing a legacy `invoices` row.
 *
 * The project module owns time-entry selection, rate grouping, and stamping
 * invoiced entries with the resulting target — this class owns only the
 * translation into a document and the one nested command call, inside the
 * same outer transaction `ProjectWorkRepository::claimInvoiceTime()` already
 * opened. It never reads or writes `project_work_items`.
 */
final readonly class LegacyProjectTimeInvoiceSource implements ProjectToInvoicePort
{
    public function __construct(private CreateInvoiceDraftFromSource $create) {}

    /**
     * @param  list<InvoiceTimeLine>  $lines
     * @param  list<string>  $timeEntryUuids
     */
    public function createDraft(int $ownerId, ProjectView $project, array $lines, array $timeEntryUuids, string $idempotencyKey): InvoiceDraftTarget
    {
        if ($lines === []) {
            throw new LogicException('Project time invoicing requires at least one line.');
        }

        $partnerId = null;
        if ($project->partnerReference !== null && preg_match('/\Alegacy-partner:(\d+)\z/D', $project->partnerReference, $m) === 1) {
            $partnerId = (int) $m[1];
        }
        $customerName = $project->name;
        if ($partnerId !== null) {
            $partner = FinancePartner::query()
                ->withoutGlobalScope('owner')
                ->where('finance_partners.user_id', $ownerId)
                ->whereKey($partnerId)
                ->firstOrFail(['id', 'name']);
            $customerName = is_string($partner->name) && trim($partner->name) !== '' ? $partner->name : $project->name;
        }

        $invoiceLines = [];
        foreach ($lines as $line) {
            if ($line->currency !== $project->currency) {
                throw new InvalidProjectAction('invoice_time_currency_mismatch');
            }
            // Project time carries no VAT rate of its own; the draft starts at 0%
            // and the user sets the correct rate while completing the draft,
            // exactly as the legacy adapter left `vatRate` unset for the same lines.
            $invoiceLines[] = new InvoiceLineData(
                $line->description,
                $this->quantity($line->hoursScaled),
                $line->hourlyRateMinor,
                0,
                'h',
                null,
                'service',
            );
        }

        $issueDate = new \DateTimeImmutable('today');
        $draft = new InvoiceDraftData(
            issueDate: $issueDate,
            dueDate: $issueDate,
            currency: $project->currency,
            customer: [
                'name' => $customerName,
                'partner_reference' => $project->partnerReference,
                'project_uuid' => $project->id->uuid,
            ],
            lines: $invoiceLines,
            discount: Discount::none($project->currency),
            partnerId: $partnerId,
        );

        $sourceKey = 'project-time:'.$project->id->uuid.':'.substr(hash('sha256', implode(',', $timeEntryUuids)), 0, 48);
        $snapshotSha256 = hash('sha256', json_encode([
            'project' => $project->id->uuid,
            'time_entries' => $timeEntryUuids,
        ], JSON_THROW_ON_ERROR));
        $source = new InvoiceDraftSource('project_time_batch', $sourceKey, 1, $snapshotSha256, $draft);

        $view = $this->create->handle($source, new IdempotencyKey('project-time-invoice:'.$idempotencyKey));

        $row = DB::table('finance_invoices as i')
            ->join('finance_document_series as s', 's.id', '=', 'i.document_series_id')
            ->where('i.user_id', $ownerId)
            ->where('i.id', $view->id->value)
            ->first(['i.current_revision_id', 's.uuid as series_uuid']);
        if ($row === null || ! is_int($row->current_revision_id) || ! is_string($row->series_uuid)) {
            throw new LogicException('Newly created invoice draft has no resolvable document series.');
        }

        return new InvoiceDraftTarget(
            'finance-invoice:'.$view->uuid,
            new ProjectDocumentSourceRef('finance_series', $row->series_uuid, $row->current_revision_id),
            '/finance/invoices/'.$view->uuid,
        );
    }

    private function quantity(int $scaled): string
    {
        $negative = $scaled < 0;
        $digits = str_pad(ltrim((string) abs($scaled), '-'), 5, '0', STR_PAD_LEFT);
        $decimal = substr($digits, 0, -4).'.'.substr($digits, -4);

        return $negative ? '-'.$decimal : $decimal;
    }
}
