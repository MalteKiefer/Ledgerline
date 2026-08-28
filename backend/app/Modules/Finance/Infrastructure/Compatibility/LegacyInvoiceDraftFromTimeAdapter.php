<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Compatibility;

use App\Models\Invoice;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\DTOs\Projects\ProjectView;
use App\Modules\Finance\Application\Ports\Projects\ProjectToInvoicePort;

final class LegacyInvoiceDraftFromTimeAdapter implements ProjectToInvoicePort
{
    public function createDraft(int $ownerId, ProjectView $project, array $lines, array $timeEntryUuids, string $idempotencyKey): InvoiceDraftTarget
    {
        $partnerId = null;
        if ($project->partnerReference !== null && preg_match('/\Alegacy-partner:(\d+)\z/D', $project->partnerReference, $m) === 1) {
            $partnerId = (int) $m[1];
        }
        $mapped = array_map(static fn ($line) => ['description' => $line->description, 'quantity_scaled' => $line->hoursScaled, 'hourly_rate_minor' => $line->hourlyRateMinor, 'value_minor' => $line->valueMinor, 'currency' => $line->currency], $lines);
        $invoice = new Invoice;
        $invoice->forceFill(['user_id' => $ownerId, 'status' => 'draft', 'type' => 'invoice', 'number' => null, 'seq' => null, 'year' => null, 'issue_date' => null, 'currency' => $project->currency, 'partner_id' => $partnerId, 'customer' => ['partner_reference' => $project->partnerReference, 'project_uuid' => $project->id->uuid], 'lines' => $mapped, 'net' => null, 'vat' => null, 'gross' => null, 'version' => 0, 'version_seq' => 0]);
        $invoice->save();
        $invoiceId = $invoice->getKey();
        if (! is_int($invoiceId)) {
            throw new \LogicException('Legacy invoice identifier is invalid.');
        }
        $reference = 'legacy-invoice:'.$invoiceId;

        return new InvoiceDraftTarget($reference, new ProjectDocumentSourceRef('legacy_invoice', $reference), 'invoices.show');
    }
}
