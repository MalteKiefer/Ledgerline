<?php

declare(strict_types=1);

namespace App\Services\Deadlines;

use App\Models\DocumentDeadline;
use App\Models\FileEntry;
use App\Models\FinanceReceipt;
use App\Models\GalleryPhoto;
use App\Models\MailMessage;
use App\Support\DeadlineReader;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Walks the four places that already hold extracted document text and records
 * the deadlines found in it.
 *
 * The text is there because those modules index it for search. Nothing reads it
 * for meaning, which is what this does — no new extraction, no new binary, no
 * new egress.
 *
 * Findings are upserted per (document, date), so re-scanning a document updates
 * what is there instead of piling up copies. A finding the user dismissed stays
 * dismissed: re-scanning must not resurrect something that was already judged
 * and rejected, or the list becomes unusable.
 */
final class DeadlineScanner
{
    public function __construct(private readonly DeadlineReader $reader = new DeadlineReader) {}

    /**
     * @return array{scanned: int, found: int, new: int}
     */
    public function scanUser(int $userId): array
    {
        $stats = ['scanned' => 0, 'found' => 0, 'new' => 0];

        foreach ($this->sources($userId) as $sourceType => $query) {
            foreach ($query->cursor() as $row) {
                $text = $this->textOf($sourceType, $row);
                $rowId = $row->getKey();
                if ($text === '' || ! is_numeric($rowId)) {
                    continue;
                }
                $stats['scanned']++;
                foreach ($this->reader->read($text) as $finding) {
                    $stats['found']++;
                    if ($this->record($userId, $sourceType, (int) $rowId, $finding, $this->labelOf($sourceType, $row))) {
                        $stats['new']++;
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * The four text columns, each read straight from its owning module.
     *
     * @return array<string, Builder<covariant \Illuminate\Database\Eloquent\Model>>
     */
    private function sources(int $userId): array
    {
        return [
            'file' => FileEntry::query()->withoutGlobalScopes()->where('user_id', $userId)
                ->whereNull('deleted_at')->whereNotNull('search_text')->select(['id', 'name', 'search_text']),
            'receipt' => FinanceReceipt::query()->withoutGlobalScopes()->where('user_id', $userId)
                ->whereNull('deleted_at')->whereNotNull('ocr')->select(['id', 'name', 'ocr']),
            'mail' => MailMessage::query()->withoutGlobalScopes()->where('user_id', $userId)
                ->whereNotNull('search_text')->select(['id', 'subject', 'search_text']),
            'photo' => GalleryPhoto::query()->withoutGlobalScopes()->where('user_id', $userId)
                ->whereNull('deleted_at')->whereNotNull('ocr_text')->select(['id', 'name', 'ocr_text']),
        ];
    }

    private function textOf(string $sourceType, object $row): string
    {
        $column = match ($sourceType) {
            'receipt' => 'ocr',
            'photo' => 'ocr_text',
            default => 'search_text',
        };
        $value = $row->{$column} ?? null;

        return is_string($value) ? $value : '';
    }

    private function labelOf(string $sourceType, object $row): ?string
    {
        $value = $sourceType === 'mail' ? ($row->subject ?? null) : ($row->name ?? null);

        return is_string($value) && $value !== '' ? mb_substr($value, 0, 300) : null;
    }

    /**
     * @param  array{due_on: string, kind: string, evidence: string}  $finding
     * @return bool true when this is a finding that did not exist yet
     */
    private function record(int $userId, string $sourceType, int $sourceId, array $finding, ?string $label): bool
    {
        $existing = DocumentDeadline::query()->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereDate('due_on', $finding['due_on'])
            ->first();

        if ($existing instanceof DocumentDeadline) {
            // Leave a judged finding alone — confirmed or dismissed, the human
            // has spoken. Only refresh the wording it was read from.
            $existing->forceFill(['evidence' => $finding['evidence'], 'label' => $label])->save();

            return false;
        }

        DB::transaction(function () use ($userId, $sourceType, $sourceId, $finding, $label): void {
            (new DocumentDeadline)->forceFill([
                'user_id' => $userId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'due_on' => $finding['due_on'],
                'kind' => $finding['kind'],
                'evidence' => $finding['evidence'],
                'label' => $label,
            ])->save();
        });

        return true;
    }
}
