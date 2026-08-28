<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Integrations\Quotes;

use App\Modules\Finance\Application\DTOs\Projects\ProjectId;
use App\Modules\Finance\Application\DTOs\Projects\ProjectQuoteSource;
use App\Modules\Finance\Application\DTOs\Projects\ProjectTarget;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Projects\ProjectFromQuoteTarget;
use App\Modules\Finance\Application\Ports\Projects\ProjectOperationRepository;
use App\Modules\Finance\Application\Ports\Projects\ProjectReferenceResolver;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\ProjectRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class FinanceQuoteProjectTarget implements ProjectFromQuoteTarget
{
    public function __construct(private Clock $clock, private ProjectReferenceResolver $references, private ProjectOperationRepository $operations) {}

    public function create(int $ownerId, ProjectQuoteSource $source, string $idempotencyKey): ProjectTarget
    {
        if (trim($idempotencyKey) === '' || strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('project_quote_request_invalid');
        }

        $requestHash = hash('sha256', json_encode(['series_uuid' => $source->seriesUuid, 'revision_id' => $source->revisionId, 'snapshot_sha256' => $source->snapshotSha256, 'number' => $source->number, 'label' => $source->label], JSON_THROW_ON_ERROR));
        $operation = $this->operations->reserve($ownerId, 'project.from_quote', $idempotencyKey, $requestHash, null);
        if ($operation->status === 'replay') {
            $uuid = $operation->result['project_uuid'] ?? null;
            if (! is_string($uuid)) {
                throw new \DomainException('operation_result_invalid');
            }

            return new ProjectTarget(new ProjectId($ownerId, $uuid), false);
        }
        if ($operation->status === 'in_progress') {
            $recovered = $this->historicalTarget($ownerId, $source);
            if ($recovered !== null) {
                $this->operations->succeed($operation, ['project_uuid' => $recovered->projectId->uuid]);

                return $recovered;
            }
            throw new \DomainException('operation_in_progress');
        }
        if ($operation->status !== 'new') {
            throw new \DomainException($operation->errorCode ?? 'operation_failed');
        }
        try {
            $target = DB::transaction(function () use ($ownerId, $source): ProjectTarget {
                $series = DocumentSeriesRecord::query()->withoutGlobalScopes()->where('user_id', $ownerId)->where('uuid', $source->seriesUuid)->where('document_type', 'quote')->lockForUpdate()->firstOrFail();
                $revision = DocumentRevisionRecord::query()->withoutGlobalScopes()->where('user_id', $ownerId)->where('document_series_id', $series->id)->whereKey($source->revisionId)->firstOrFail();
                $quoteSeries = DB::table('finance_quote_series')->where('user_id', $ownerId)->where('document_series_id', $series->id)->lockForUpdate()->first();
                if ($quoteSeries === null) {
                    throw new InvalidArgumentException('project_quote_series_missing');
                }
                $snapshot = $revision->snapshot;
                if (! is_array($snapshot) || $snapshot !== $source->snapshot || ! hash_equals(hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), $source->snapshotSha256)) {
                    throw new InvalidArgumentException('project_quote_snapshot_mismatch');
                }
                $expectedNumber = is_string($quoteSeries->number) ? $quoteSeries->number : null;
                $expectedLabel = $expectedNumber !== null ? 'Quote '.$expectedNumber : $source->title;
                $storedPartnerId = $quoteSeries->partner_id;
                if ($storedPartnerId !== null && ! is_int($storedPartnerId)) {
                    throw new InvalidArgumentException('project_quote_snapshot_mismatch');
                }
                if ($source->number !== $expectedNumber || $source->label !== $expectedLabel || $source->partnerId !== $storedPartnerId || (int) $revision->net_minor !== $source->netMinor || (int) $revision->vat_minor !== $source->vatMinor || (int) $revision->gross_minor !== $source->grossMinor || (string) $revision->currency !== $source->currency || ($snapshot['lines'] ?? null) !== $source->lines) {
                    throw new InvalidArgumentException('project_quote_snapshot_mismatch');
                }
                $this->references->assertOwnedPartnerReference($ownerId, $source->partnerReference);
                foreach ($source->lines as $line) {
                    $this->references->assertOwnedProductReference($ownerId, $line['product_id'] !== null ? 'legacy-product:'.$line['product_id'] : null);
                }
                $existing = DB::table('finance_project_document_links')->where('user_id', $ownerId)->where('document_series_id', $series->id)->where('pinned_revision_id', $revision->id)->where('role', 'source_quote')->orderBy('id')->first();
                if ($existing) {
                    $project = ProjectRecord::query()->withoutGlobalScopes()->where('user_id', $ownerId)->whereKey($existing->project_id)->firstOrFail();

                    return new ProjectTarget(new ProjectId($ownerId, (string) $project->uuid), false);
                }
                $now = $this->clock->now();
                $timestamp = $now->format('Y-m-d H:i:s.u');
                $uuid = (string) Str::uuid();
                $projectId = (int) DB::table('finance_project_records')->insertGetId(['user_id' => $ownerId, 'uuid' => $uuid, 'parent_project_id' => null, 'source_type' => null, 'source_id' => null, 'name' => trim($source->title), 'kind' => 'business', 'status' => 'planned', 'partner_reference' => $source->partnerReference, 'starts_on' => $source->issuedOn?->format('Y-m-d'), 'due_on' => $source->validUntil?->format('Y-m-d'), 'budget_minor' => $source->netMinor, 'currency' => $source->currency, 'version' => 0, 'archived_at' => null, 'created_by' => $ownerId, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
                DB::table('finance_project_document_links')->insert(['user_id' => $ownerId, 'project_id' => $projectId, 'source_type' => 'finance_series', 'source_reference' => $source->seriesUuid, 'document_series_id' => $series->id, 'pinned_revision_id' => $revision->id, 'role' => 'source_quote', 'metadata_snapshot' => json_encode(['number' => $source->number, 'label' => $source->label, 'snapshot_sha256' => $source->snapshotSha256], JSON_THROW_ON_ERROR), 'attached_by' => $ownerId, 'attached_at' => $timestamp, 'detached_by' => null, 'detached_at' => null]);
                $this->activity($ownerId, $projectId, 'project.created_from_quote', ['revision_id' => $source->revisionId], $timestamp);
                $this->activity($ownerId, $projectId, 'project.document_attached', ['role' => 'source_quote'], $timestamp);
                $sort = 0;
                foreach ($source->lines as $index => $line) {
                    if ($line['kind'] !== 'service' || trim($line['description']) === '') {
                        continue;
                    } [$title,$description] = $this->description($line['description']);
                    $workUuid = (string) Str::uuid();
                    DB::table('finance_project_work_items')->insert(['user_id' => $ownerId, 'project_id' => $projectId, 'uuid' => $workUuid, 'title' => $title, 'description' => $description, 'status' => 'open', 'starts_on' => null, 'due_on' => null, 'estimate_quantity_scaled' => $line['quantity_scaled'], 'is_milestone' => false, 'sort' => $sort++, 'source_revision_id' => $revision->id, 'source_line_index' => $index, 'product_reference' => $line['product_id'] !== null ? 'legacy-product:'.$line['product_id'] : null, 'version' => 0, 'created_by' => $ownerId, 'deleted_at' => null, 'created_at' => $timestamp, 'updated_at' => $timestamp]);
                    $this->activity($ownerId, $projectId, 'work_item.created', ['work_item_uuid' => $workUuid, 'source_line_index' => $index], $timestamp);
                }

                return new ProjectTarget(new ProjectId($ownerId, $uuid), true);
            }, 1);
            $this->operations->succeed($operation, ['project_uuid' => $target->projectId->uuid]);

            return $target;
        } catch (\Throwable $exception) {
            $this->operations->fail($operation, 'project_from_quote_failed');
            throw $exception;
        }
    }

    /** @return array{string, ?string} */
    private function description(string $text): array
    {
        $parts = preg_split('/\R/', $text) ?: [];
        $title = trim((string) array_shift($parts));
        if ($title === '') {
            throw new InvalidArgumentException('project_quote_service_title_invalid');
        } $description = trim(implode("\n", $parts));

        return [$title, $description === '' ? null : $description];
    }

    private function historicalTarget(int $ownerId, ProjectQuoteSource $source): ?ProjectTarget
    {
        $link = DB::table('finance_project_document_links as links')->join('finance_document_series as series', 'series.id', '=', 'links.document_series_id')->join('finance_project_records as projects', 'projects.id', '=', 'links.project_id')->where('links.user_id', $ownerId)->where('series.user_id', $ownerId)->where('series.uuid', $source->seriesUuid)->where('links.pinned_revision_id', $source->revisionId)->where('links.role', 'source_quote')->orderBy('links.id')->select('projects.uuid')->first();

        return $link !== null && is_string($link->uuid) ? new ProjectTarget(new ProjectId($ownerId, $link->uuid), false) : null;
    }

    /** @param array<string, mixed> $payload */
    private function activity(int $ownerId, int $projectId, string $type, array $payload, string $timestamp): void
    {
        DB::table('finance_project_activities')->insert(['user_id' => $ownerId, 'project_id' => $projectId, 'type' => $type, 'subject_type' => null, 'subject_reference' => null, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_by' => $ownerId, 'occurred_at' => $timestamp, 'created_at' => $timestamp]);
    }
}
