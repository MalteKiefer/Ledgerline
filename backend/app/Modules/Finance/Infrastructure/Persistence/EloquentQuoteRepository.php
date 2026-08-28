<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence;

use App\Modules\Finance\Application\DTOs\Quotes\QuoteId;
use App\Modules\Finance\Application\DTOs\Quotes\QuotePage;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteRevisionRef;
use App\Modules\Finance\Application\DTOs\Quotes\QuoteView;
use App\Modules\Finance\Application\Ports\Clock;
use App\Modules\Finance\Application\Ports\Quotes\QuoteRepository;
use App\Modules\Finance\Application\Ports\Quotes\QuoteSettings;
use App\Modules\Finance\Domain\Shared\DocumentTotals;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentRevisionRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\DocumentSeriesRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteDraftRecord;
use App\Modules\Finance\Infrastructure\Persistence\Models\QuoteSeriesRecord;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class EloquentQuoteRepository implements QuoteRepository
{
    public function __construct(
        private readonly Clock $clock,
        private readonly QuoteSettings $settings,
    ) {}

    public function get(QuoteId $id): QuoteView
    {
        return $this->viewForUuid($id);
    }

    public function page(array $filters, int $page, int $perPage): QuotePage
    {
        $ownerId = $filters['owner_id'] ?? null;

        if (! is_int($ownerId) || $ownerId < 1) {
            throw new InvalidArgumentException('Quote pages require a positive owner_id filter.');
        }

        if ($page < 1 || $perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('Quote pagination must use page >= 1 and perPage between 1 and 100.');
        }

        $quotes = QuoteSeriesRecord::query()
            ->ownedBy($ownerId)
            ->whereHas('series', static function ($query) use ($ownerId): void {
                $query->where('user_id', $ownerId)->where('document_type', 'quote');
            })
            ->with(['series', 'draft', 'currentRevision'])
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByDesc('published_at')
            ->orderByDesc('document_series_id')
            ->get()
            ->map(fn (QuoteSeriesRecord $quote): QuoteView => $this->viewFromRecord($ownerId, $quote))
            ->filter(fn (QuoteView $quote): bool => $this->matchesFilters($quote, $filters))
            ->values();
        $total = $quotes->count();

        return new QuotePage(
            array_values($quotes->slice(($page - 1) * $perPage, $perPage)->all()),
            $page,
            $perPage,
            $total,
        );
    }

    public function revisions(QuoteId $id): array
    {
        $series = $this->seriesForUuid($id);

        return array_values(DocumentRevisionRecord::query()
            ->ownedBy($id->ownerId)
            ->where('document_series_id', $series->id)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DocumentRevisionRecord $revision): QuoteRevisionRef => $this->revisionRef($revision))
            ->all());
    }

    public function createDraft(int $ownerId, array $payload, DocumentTotals $totals): QuoteView
    {
        if ($ownerId < 1) {
            throw new LogicException('Quote drafts require a positive owner ID.');
        }

        return DB::transaction(function () use ($ownerId, $payload, $totals): QuoteView {
            $series = new DocumentSeriesRecord;
            $series->forceFill([
                'user_id' => $ownerId,
                'uuid' => (string) Str::uuid(),
                'document_type' => 'quote',
                'status' => 'draft',
                'source_type' => null,
                'source_id' => null,
                'created_by' => $ownerId,
            ]);
            $series->save();

            $quote = new QuoteSeriesRecord;
            $quote->forceFill([
                'document_series_id' => $series->id,
                'user_id' => $ownerId,
                'document_type' => 'quote',
                'partner_id' => null,
                'current_revision_id' => null,
                'number' => null,
                'sequence_year' => null,
                'sequence_number' => null,
                'version' => 0,
                'published_at' => null,
                'accepted_at' => null,
                'declined_at' => null,
                'converted_at' => null,
            ]);
            $quote->save();

            $draft = new QuoteDraftRecord;
            $draft->forceFill([
                'document_series_id' => $series->id,
                'user_id' => $ownerId,
                'based_on_revision_id' => null,
                ...$this->draftValues($payload, $totals),
                'updated_by' => $ownerId,
            ]);
            $draft->save();

            return $this->viewForUuid(new QuoteId($ownerId, (string) $series->uuid));
        }, 1);
    }

    public function updateDraft(
        QuoteId $id,
        int $expectedVersion,
        array $payload,
        DocumentTotals $totals,
    ): QuoteView {
        return DB::transaction(function () use ($id, $expectedVersion, $payload, $totals): QuoteView {
            $series = DocumentSeriesRecord::query()
                ->ownedBy($id->ownerId)
                ->where('uuid', $id->uuid)
                ->where('document_type', 'quote')
                ->lockForUpdate()
                ->firstOrFail();
            $quote = QuoteSeriesRecord::query()
                ->ownedBy($id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quote->current_revision_id !== null) {
                DocumentRevisionRecord::query()
                    ->ownedBy($id->ownerId)
                    ->where('document_series_id', $series->id)
                    ->whereKey($quote->current_revision_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $draft = QuoteDraftRecord::query()
                ->ownedBy($id->ownerId)
                ->where('document_series_id', $series->id)
                ->lockForUpdate()
                ->firstOrFail();

            $updated = DB::table('finance_quote_series')
                ->where('user_id', $id->ownerId)
                ->where('document_series_id', $series->id)
                ->where('version', $expectedVersion)
                ->update([
                    'version' => $expectedVersion + 1,
                    'updated_at' => now(),
                ]);

            if ($updated === 1) {
                $draft->forceFill([
                    ...$this->draftValues($payload, $totals),
                    'updated_by' => $id->ownerId,
                ])->save();
            }

            return $this->viewForUuid($id);
        }, 1);
    }

    private function seriesForUuid(QuoteId $id): DocumentSeriesRecord
    {
        return DocumentSeriesRecord::query()
            ->ownedBy($id->ownerId)
            ->where('uuid', $id->uuid)
            ->where('document_type', 'quote')
            ->firstOrFail();
    }

    private function viewForUuid(QuoteId $id): QuoteView
    {
        $series = $this->seriesForUuid($id);
        $quote = QuoteSeriesRecord::query()
            ->ownedBy($id->ownerId)
            ->with(['draft', 'currentRevision'])
            ->where('document_series_id', $series->id)
            ->firstOrFail();

        return $this->viewFromRecord($id->ownerId, $quote);
    }

    private function viewFromRecord(int $ownerId, QuoteSeriesRecord $quote): QuoteView
    {
        $series = $quote->series;

        if (! $series instanceof DocumentSeriesRecord
            || (int) $series->user_id !== $ownerId
            || $series->document_type !== 'quote') {
            throw new LogicException('Quote series ownership is inconsistent.');
        }

        $draft = $quote->draft;
        $currentRevision = $quote->currentRevision;
        $payload = $draft?->getAttribute('payload');
        $draftPayload = is_array($payload) ? $payload : null;
        $totalsSource = $draft ?? $currentRevision;

        if ($totalsSource === null) {
            throw new LogicException('Quote aggregate has neither a draft nor a current revision.');
        }

        return new QuoteView(
            new QuoteId($ownerId, (string) $series->uuid),
            (string) $series->status,
            $this->effectiveStatus($ownerId, (string) $series->status, $currentRevision),
            $quote->partner_id !== null ? (int) $quote->partner_id : null,
            is_string($quote->number) ? $quote->number : null,
            (int) $quote->version,
            $currentRevision !== null ? $this->revisionRef($currentRevision) : null,
            $draftPayload,
            (int) $totalsSource->net_minor,
            (int) $totalsSource->vat_minor,
            (int) $totalsSource->gross_minor,
            (string) $totalsSource->currency,
            $this->date($quote->published_at),
            $this->date($quote->accepted_at),
            $this->date($quote->declined_at),
            $this->date($quote->converted_at),
            $this->requiredDate($series->created_at),
            $this->requiredDate($series->updated_at),
        );
    }

    /** @param array<string, mixed> $filters */
    private function matchesFilters(QuoteView $quote, array $filters): bool
    {
        $query = $filters['q'] ?? null;
        if (is_string($query) && trim($query) !== '') {
            $needle = trim($query);
            $draft = $quote->draft !== null
                ? json_encode($quote->draft, JSON_THROW_ON_ERROR)
                : '';
            $haystack = implode("\n", [
                $quote->id->uuid,
                $quote->number ?? '',
                $draft,
            ]);

            if (stripos($haystack, $needle) === false) {
                return false;
            }
        }

        if (isset($filters['status']) && $filters['status'] !== $quote->status) {
            return false;
        }

        if (isset($filters['effective_status'])
            && $filters['effective_status'] !== $quote->effectiveStatus) {
            return false;
        }

        if ($quote->publishedAt === null) {
            return ! isset($filters['published_from']) && ! isset($filters['published_to']);
        }

        $from = $filters['published_from'] ?? null;
        if (is_string($from)
            && $quote->publishedAt < new DateTimeImmutable($from.' 00:00:00')) {
            return false;
        }

        $to = $filters['published_to'] ?? null;

        return ! is_string($to)
            || $quote->publishedAt <= new DateTimeImmutable($to.' 23:59:59.999999');
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{payload: array<array-key, mixed>, net_minor: int, vat_minor: int, gross_minor: int, currency: string}
     */
    private function draftValues(array $payload, DocumentTotals $totals): array
    {
        $currency = $totals->net->currency();

        if ($totals->vat->currency() !== $currency || $totals->gross->currency() !== $currency) {
            throw new LogicException('Quote totals must use one currency.');
        }

        return [
            'payload' => $payload,
            'net_minor' => $totals->net->minor(),
            'vat_minor' => $totals->vat->minor(),
            'gross_minor' => $totals->gross->minor(),
            'currency' => $currency,
        ];
    }

    private function revisionRef(DocumentRevisionRecord $revision): QuoteRevisionRef
    {
        $snapshot = $revision->getAttribute('snapshot');

        if (! is_array($snapshot)) {
            throw new LogicException('Quote revision snapshot must be an array.');
        }

        return new QuoteRevisionRef(
            (int) $revision->id,
            (int) $revision->revision_number,
            $revision->previous_revision_id !== null ? (int) $revision->previous_revision_id : null,
            (string) $revision->status,
            $snapshot,
            (int) $revision->net_minor,
            (int) $revision->vat_minor,
            (int) $revision->gross_minor,
            (string) $revision->currency,
            is_string($revision->pdf_path) ? $revision->pdf_path : null,
            is_string($revision->pdf_sha256) ? $revision->pdf_sha256 : null,
            $this->date($revision->published_at),
            $this->requiredDate($revision->created_at),
        );
    }

    private function effectiveStatus(
        int $ownerId,
        string $status,
        ?DocumentRevisionRecord $revision,
    ): string {
        if ($status !== 'sent' || $revision === null) {
            return $status;
        }

        $snapshot = $revision->getAttribute('snapshot');
        $validUntil = is_array($snapshot) ? ($snapshot['valid_until'] ?? null) : null;

        if (is_string($validUntil)) {
            $timezone = new DateTimeZone($this->settings->ownerTimezone($ownerId));
            $validThrough = new DateTimeImmutable($validUntil, $timezone)
                ->setTime(23, 59, 59, 999_999);
            $now = $this->clock->now()->setTimezone($timezone);

            if ($now > $validThrough) {
                return 'expired';
            }
        }

        return $status;
    }

    private function requiredDate(mixed $value): DateTimeImmutable
    {
        return $this->date($value)
            ?? throw new LogicException('Quote timestamp is missing.');
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : null;
    }
}
