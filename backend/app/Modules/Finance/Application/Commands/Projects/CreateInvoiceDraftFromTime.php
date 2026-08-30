<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Projects;

use App\Modules\Finance\Application\DTOs\Projects\InvoiceDraftTarget;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceTimeData;
use App\Modules\Finance\Application\DTOs\Projects\InvoiceTimeLine;
use App\Modules\Finance\Application\DTOs\Projects\ProjectDocumentSourceRef;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectToInvoicePort;
use App\Modules\Finance\Application\Ports\Projects\ProjectWorkRepository;
use App\Modules\Finance\Application\Services\Projects\ProjectDataValidator;
use App\Modules\Finance\Domain\Projects\Exception\InvalidProjectAction;
use App\Modules\Finance\Domain\Projects\ProjectWorkflow;
use App\Modules\Finance\Domain\Projects\TimeCharge;
use App\Modules\Finance\Domain\Shared\DecimalQuantity;
use App\Modules\Finance\Domain\Shared\Money;
use DomainException;

final readonly class CreateInvoiceDraftFromTime
{
    public function __construct(private ProjectWorkRepository $work, private ProjectRepository $projects, private ProjectOperationRepository $operations, private ProjectToInvoicePort $invoices, private ProjectDataValidator $validator, private ProjectWorkflow $workflow) {}

    public function handle(InvoiceTimeData $data): InvoiceDraftTarget
    {
        $this->validator->actor($data->projectId, $data->actorId);
        $project = $this->projects->get($data->projectId);
        $this->workflow->assertNotArchived($project->archived);
        $hash = hash('sha256', json_encode(['project' => $data->projectId->uuid, 'time' => $data->timeEntryUuids], JSON_THROW_ON_ERROR));
        $reservation = $this->operations->reserve($data->projectId->ownerId, 'project.invoice_time', $data->idempotencyKey, $hash, $data->projectId);
        if ($reservation->status === 'in_progress') {
            throw new DomainException('operation_in_progress');
        }
        if ($reservation->status === 'failed') {
            $reservation = $this->operations->retryFailed($reservation);
        }
        $claimReference = 'project-invoice-operation:'.$reservation->recordId;
        if ($reservation->status === 'replay') {
            $result = $reservation->result ?? throw new DomainException('operation_result_missing');
            foreach (['target_reference', 'source_type', 'source_reference'] as $key) {
                if (! isset($result[$key]) || ! is_string($result[$key])) {
                    throw new DomainException('operation_result_invalid');
                }
            }
            $revisionId = $result['pinned_revision_id'] ?? null;
            if ($revisionId !== null && ! is_int($revisionId)) {
                throw new DomainException('operation_result_invalid');
            }
            $target = new InvoiceDraftTarget($result['target_reference'], new ProjectDocumentSourceRef($result['source_type'], $result['source_reference'], $revisionId), isset($result['navigation']) && is_string($result['navigation']) ? $result['navigation'] : null);
            $this->work->stampInvoicedTime($data->projectId, $data->timeEntryUuids, $claimReference, $target, $data->actorId, $data->occurredAt);

            return $target;
        }
        try {
            $entries = $this->work->claimInvoiceTime($data->projectId, $data->timeEntryUuids, $claimReference, $data->occurredAt);
        } catch (\Throwable $exception) {
            $this->operations->fail($reservation, self::errorCode($exception));
            throw $exception;
        }
        try {
            /** @var array<string, array{hours: int, rate: int, currency: string}> $groups */
            $groups = [];
            foreach ($entries as $entry) {
                if (! $entry->billable || $entry->hourlyRateMinor === null || $entry->invoiceTargetReference !== $claimReference) {
                    throw new DomainException('time_entry_not_invoiceable');
                }
                $key = $entry->currency.':'.$entry->hourlyRateMinor;
                $groups[$key] ??= ['hours' => 0, 'rate' => $entry->hourlyRateMinor, 'currency' => $entry->currency];
                $groups[$key]['hours'] = self::checkedAdd($groups[$key]['hours'], $entry->quantityScaled);
            }
            $lines = [];
            foreach ($groups as $group) {
                $hours = DecimalQuantity::fromString(self::decimal($group['hours']));
                $value = TimeCharge::calculate($hours, Money::fromMinor($group['rate'], $group['currency']))->minor();
                $lines[] = new InvoiceTimeLine($group['hours'], $group['rate'], $value, $group['currency'], 'Project time');
            }
            $target = $this->invoices->createDraft($data->projectId->ownerId, $project, $lines, $data->timeEntryUuids, $data->idempotencyKey);
            $this->operations->succeed($reservation, ['target_reference' => $target->targetReference, 'source_type' => $target->source->sourceType, 'source_reference' => $target->source->sourceReference, 'pinned_revision_id' => $target->source->pinnedRevisionId, 'navigation' => $target->navigationCapability]);
        } catch (\Throwable $exception) {
            $this->operations->fail($reservation, self::errorCode($exception));
            throw $exception;
        }
        $this->work->stampInvoicedTime($data->projectId, $data->timeEntryUuids, $claimReference, $target, $data->actorId, $data->occurredAt);

        return $target;
    }

    private static function decimal(int $scaled): string
    {
        $raw = (string) $scaled;
        $sign = str_starts_with($raw, '-') ? '-' : '';
        $digits = ltrim($raw, '-');
        $digits = str_pad($digits, 5, '0', STR_PAD_LEFT);

        return $sign.substr($digits, 0, -4).'.'.substr($digits, -4);
    }

    private static function checkedAdd(int $left, int $right): int
    {
        if (($right > 0 && $left > PHP_INT_MAX - $right) || ($right < 0 && $left < PHP_INT_MIN - $right)) {
            throw new DomainException('project_total_overflow');
        }

        return $left + $right;
    }

    private static function errorCode(\Throwable $exception): string
    {
        $code = $exception instanceof InvalidProjectAction ? $exception->errorCode : $exception->getMessage();

        return preg_match('/\A[a-z0-9_.-]{1,64}\z/D', $code) === 1 ? $code : 'invoice_time_failed';
    }
}
