<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\Commands\Recurring;

use App\Modules\Finance\Application\Commands\Invoices\CreateInvoiceDraftFromSource;
use App\Modules\Finance\Application\Commands\Invoices\FinalizeInvoice;
use App\Modules\Finance\Application\Commands\Invoices\QueueInvoiceDelivery;
use App\Modules\Finance\Application\DTOs\IdempotencyKey;
use App\Modules\Finance\Application\DTOs\Invoices\DeliveryId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftData;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceDraftSource;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceId;
use App\Modules\Finance\Application\DTOs\Invoices\InvoiceLineData;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringRunId;
use App\Modules\Finance\Application\DTOs\Recurring\RecurringTemplateId;
use App\Modules\Finance\Application\Ports\InvoiceRepository;
use App\Modules\Finance\Application\Ports\RecurringInvoiceRepository;
use App\Modules\Finance\Domain\Shared\Discount;
use App\Modules\Finance\Domain\Shared\Money;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Advances one recurring run by exactly the steps it can complete right
 * now, inspecting persisted invoice/revision/delivery state before acting
 * so a resumed or retried call never repeats a completed step, never
 * creates a second invoice, and never re-finalizes an already finalized
 * revision. `draft` mode templates stop after `draft_created`; `auto_send`
 * templates continue through finalization and delivery staging. The final
 * "mail actually sent" step is async (`SendInvoiceDeliveryJob`); this
 * command only observes that outcome, it never drives it directly.
 */
final readonly class ProcessRecurringInvoiceRun
{
    public function __construct(
        private RecurringInvoiceRepository $templates,
        private InvoiceRepository $invoices,
        private CreateInvoiceDraftFromSource $createDraft,
        private FinalizeInvoice $finalize,
        private QueueInvoiceDelivery $queueDelivery,
    ) {}

    public function handle(RecurringRunId $id): string
    {
        while (true) {
            $run = $this->templates->run($id);
            $status = $this->runStatus($run);

            if (in_array($status, ['sent', 'failed'], true)) {
                return $status;
            }

            $templateId = $run['template_id'];
            if (! is_int($templateId)) {
                throw new LogicException('Recurring run template reference is invalid.');
            }
            $template = $this->templates->template(new RecurringTemplateId($templateId));
            $modeValue = $template['mode'];
            if (! is_string($modeValue)) {
                throw new LogicException('Recurring template mode is invalid.');
            }
            $mode = $modeValue;

            if ($status === 'draft_created' && $mode === 'draft') {
                return $status;
            }

            if ($status === 'sending') {
                return $this->pollDelivery($id, $run);
            }

            // The resume point is the persisted `last_completed_step`, not the raw
            // status: a retried run always sits at status `pending` again, but it
            // must resume exactly where it left off (never redo a completed step,
            // never create a second invoice, never re-finalize a finalized
            // revision), and only `last_completed_step` still says where that is.
            $stepValue = $run['last_completed_step'];
            if ($stepValue !== null && ! is_string($stepValue)) {
                throw new LogicException('Recurring run progress step is invalid.');
            }
            $step = $stepValue;

            if ($step === null) {
                $this->driveDraft($id, $run);

                continue;
            }

            if ($step === 'draft_created') {
                $this->driveFinalize($id, $run);

                continue;
            }

            if ($step === 'finalized') {
                $this->driveDelivery($id, $run);

                continue;
            }

            if ($step === 'delivery_staged') {
                // The delivery was already staged before a mail-step failure; resume
                // watching it instead of queuing a second delivery.
                if ($status !== 'sending') {
                    $this->templates->transitionRun($id, 'sending', null, null, null, null, null);
                }

                continue;
            }

            throw new LogicException('Recurring run progress step is not a recognised, actionable state.');
        }
    }

    /** @param array<string, mixed> $run */
    private function driveDraft(RecurringRunId $id, array $run): void
    {
        if ($this->runStatus($run) !== 'creating_draft') {
            $this->templates->transitionRun($id, 'creating_draft', null, null, null, null, null);
        }

        $templateId = $run['template_id'];
        if (! is_int($templateId)) {
            throw new LogicException('Recurring run template reference is invalid.');
        }
        $version = $this->templates->versionForOccurrence(new RecurringTemplateId($templateId), $this->localDate($run));
        $source = $this->buildSource($run, $version);
        $key = new IdempotencyKey('recurring-run:'.$this->runUuid($run).':draft');

        try {
            $invoice = $this->createDraft->handle($source, $key);
        } catch (Throwable $exception) {
            $this->fail($id, $exception);

            throw $exception;
        }

        $this->templates->transitionRun($id, 'draft_created', 'draft_created', $invoice->id->value, null, null, null);
    }

    /** @param array<string, mixed> $run */
    private function driveFinalize(RecurringRunId $id, array $run): void
    {
        if ($this->runStatus($run) !== 'finalizing') {
            $this->templates->transitionRun($id, 'finalizing', null, null, null, null, null);
        }

        $invoiceId = $run['invoice_id'];
        if (! is_int($invoiceId)) {
            throw new LogicException('Recurring run has no draft invoice to finalize.');
        }
        $key = new IdempotencyKey('recurring-run:'.$this->runUuid($run).':finalize');

        try {
            $this->finalize->handle(new InvoiceId($invoiceId), $key);
        } catch (Throwable $exception) {
            $this->fail($id, $exception);

            throw $exception;
        }

        $this->templates->transitionRun($id, 'finalized', 'finalized', null, null, null, null);
    }

    /** @param array<string, mixed> $run */
    private function driveDelivery(RecurringRunId $id, array $run): void
    {
        $invoiceId = $run['invoice_id'];
        if (! is_int($invoiceId)) {
            throw new LogicException('Recurring run has no finalized invoice to deliver.');
        }
        $key = new IdempotencyKey('recurring-run:'.$this->runUuid($run).':delivery');

        try {
            $deliveryId = $this->queueDelivery->handle(new InvoiceId($invoiceId), null, $key);
        } catch (Throwable $exception) {
            $this->fail($id, $exception);

            throw $exception;
        }

        $this->templates->transitionRun($id, 'sending', 'delivery_staged', null, $deliveryId->value, null, null);
    }

    /**
     * @param  array<string, mixed>  $run
     */
    private function pollDelivery(RecurringRunId $id, array $run): string
    {
        $deliveryId = $run['delivery_id'];
        if (! is_int($deliveryId)) {
            throw new LogicException('Recurring run has no staged delivery to poll.');
        }

        $status = $this->invoices->deliveryStatus(new DeliveryId($deliveryId));
        $deliveryStatus = $status['status'];
        if (! is_string($deliveryStatus)) {
            throw new LogicException('Invoice delivery status is invalid.');
        }

        $errorCode = $status['last_error_code'] ?? 'invoice_delivery_failed';
        if (! is_string($errorCode)) {
            $errorCode = 'invoice_delivery_failed';
        }

        return match ($deliveryStatus) {
            'sent' => $this->runStatus($this->templates->transitionRun($id, 'sent', 'sent', null, null, null, null)),
            'failed' => $this->runStatus($this->templates->transitionRun(
                $id,
                'failed',
                null,
                null,
                null,
                $errorCode,
                'The staged invoice delivery failed permanently.',
            )),
            default => 'sending',
        };
    }

    /** @param array<string, mixed> $run */
    private function runStatus(array $run): string
    {
        $status = $run['status'];
        if (! is_string($status)) {
            throw new LogicException('Recurring run status is invalid.');
        }

        return $status;
    }

    /** @param array<string, mixed> $run */
    private function runUuid(array $run): string
    {
        $uuid = $run['uuid'];
        if (! is_string($uuid)) {
            throw new LogicException('Recurring run identity is invalid.');
        }

        return $uuid;
    }

    private function fail(RecurringRunId $id, Throwable $exception): void
    {
        $this->templates->transitionRun(
            $id,
            'failed',
            null,
            null,
            null,
            $this->safeErrorCode($exception),
            substr($exception::class, 0, 512),
        );
    }

    private function safeErrorCode(Throwable $exception): string
    {
        if ($exception instanceof DomainException || $exception instanceof InvalidArgumentException) {
            $message = $exception->getMessage();
            if (preg_match('/\A[a-z0-9_]{1,128}\z/D', $message) === 1) {
                return $message;
            }
        }

        return 'recurring_run_step_failed';
    }

    /** @param array<string, mixed> $run */
    private function localDate(array $run): DateTimeImmutable
    {
        $value = $run['scheduled_local_date'];

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (! is_string($value)) {
            throw new LogicException('Recurring run scheduled date is invalid.');
        }

        return new DateTimeImmutable($value);
    }

    /**
     * @param  array<string, mixed>  $run
     * @param  array{id: int, version_number: int, effective_from: string, draft_snapshot: array<string, mixed>, snapshot_sha256: string}  $version
     */
    private function buildSource(array $run, array $version): InvoiceDraftSource
    {
        $snapshot = $version['draft_snapshot'];
        $issueDateSource = new DateTimeImmutable($this->snapshotString($snapshot, 'issue_date'));
        $dueDateSource = new DateTimeImmutable($this->snapshotString($snapshot, 'due_date'));
        $termDays = (int) $issueDateSource->diff($dueDateSource)->days;

        $issueDate = $this->localDate($run);
        $dueDate = $issueDate->modify(sprintf('+%d days', $termDays));

        $currency = $this->snapshotString($snapshot, 'currency');
        $discountData = $this->snapshotArray($snapshot, 'discount');
        $discountBasisPoints = $this->snapshotInt($discountData, 'basis_points');
        $discountFixedMinor = $this->snapshotInt($discountData, 'fixed_minor');
        $discount = match (true) {
            $discountBasisPoints !== 0 => Discount::percentBasisPoints($discountBasisPoints, $currency),
            $discountFixedMinor !== 0 => Discount::fixed(Money::fromMinor($discountFixedMinor, $currency)),
            default => Discount::none($currency),
        };

        $rawLines = $snapshot['lines'] ?? null;
        if (! is_array($rawLines)) {
            throw new LogicException('Recurring template snapshot lines are invalid.');
        }

        $lines = array_values(array_map(function (mixed $line): InvoiceLineData {
            $line = $this->stringKeyedArray($line);
            $productId = $line['product_id'] ?? null;
            $kind = $line['kind'] ?? null;

            return new InvoiceLineData(
                $this->snapshotString($line, 'description'),
                $this->snapshotString($line, 'quantity'),
                $this->snapshotInt($line, 'unit_price_minor'),
                $this->snapshotInt($line, 'tax_rate_basis_points'),
                $this->snapshotString($line, 'unit'),
                $productId === null ? null : $this->snapshotInt($line, 'product_id'),
                $kind === null ? null : $this->snapshotString($line, 'kind'),
            );
        }, $rawLines));

        $totals = $this->snapshotArray($snapshot, 'totals');
        $partnerId = $snapshot['partner_id'] ?? null;
        $projectId = $snapshot['project_id'] ?? null;

        $draft = new InvoiceDraftData(
            issueDate: $issueDate,
            dueDate: $dueDate,
            currency: $currency,
            customer: $this->stringKeyedArrayOrEmpty($snapshot['customer'] ?? null),
            lines: $lines,
            discount: $discount,
            controlNetMinor: $this->snapshotInt($totals, 'net_minor'),
            controlVatMinor: $this->snapshotInt($totals, 'vat_minor'),
            controlGrossMinor: $this->snapshotInt($totals, 'gross_minor'),
            partnerId: $partnerId === null ? null : $this->snapshotInt($snapshot, 'partner_id'),
            projectId: $projectId === null ? null : $this->snapshotInt($snapshot, 'project_id'),
        );

        return new InvoiceDraftSource(
            sourceType: 'recurring_run',
            sourceKey: 'recurring-run:'.$this->runUuid($run),
            sourceRevisionId: $version['id'],
            sourceSnapshotSha256: $version['snapshot_sha256'],
            draft: $draft,
        );
    }

    /** @param array<string, mixed> $data */
    private function snapshotString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value)) {
            throw new LogicException("Recurring template snapshot field \"{$key}\" is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function snapshotInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (! is_int($value)) {
            throw new LogicException("Recurring template snapshot field \"{$key}\" is invalid.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function snapshotArray(array $data, string $key): array
    {
        return $this->stringKeyedArray($data[$key] ?? null);
    }

    /** @return array<string, mixed> */
    private function stringKeyedArray(mixed $value): array
    {
        if (! is_array($value)) {
            throw new LogicException('Recurring template snapshot field is invalid.');
        }

        return $this->stringKeyedArrayOrEmpty($value);
    }

    /** @return array<string, mixed> */
    private function stringKeyedArrayOrEmpty(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }
}
