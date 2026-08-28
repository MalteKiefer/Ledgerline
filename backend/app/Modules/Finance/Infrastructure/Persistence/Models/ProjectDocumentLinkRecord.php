<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Exception\AppendOnlyRecordMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectDocumentLinkRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_project_document_links';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'project_id' => 'integer',
            'document_series_id' => 'integer', 'pinned_revision_id' => 'integer',
            'metadata_snapshot' => 'array', 'attached_by' => 'integer',
            'attached_at' => 'immutable_datetime', 'detached_by' => 'integer',
            'detached_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (self $link): void {
            $link->assertOneWayDetach();
        });
        self::deleting(static function (): void {
            throw AppendOnlyRecordMutation::projectDocumentLink();
        });
    }

    /** @return GuardedMutationBuilder<ProjectDocumentLinkRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel(
            $query,
            self::class,
            static function (GuardedMutationBuilder $builder, string $operation, array $values): void {
                if (in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                    return;
                }
                if ($operation !== 'update') {
                    throw AppendOnlyRecordMutation::projectDocumentLink();
                }

                self::assertDetachValues($values);
                $matched = (clone $builder)->lockForUpdate()->get(['detached_at']);
                if ($matched->contains(
                    static fn (self $link): bool => $link->getRawOriginal('detached_at') !== null,
                )) {
                    throw AppendOnlyRecordMutation::projectDocumentLink();
                }
                $builder->whereNull($builder->getModel()->qualifyColumn('detached_at'));
            },
        );
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        $this->assertOneWayDetach();

        return parent::performUpdate($query);
    }

    protected function performDeleteOnModel()
    {
        throw AppendOnlyRecordMutation::projectDocumentLink();
    }

    private function assertOneWayDetach(): void
    {
        self::assertDetachValues($this->getDirty());
        if ($this->getRawOriginal('detached_at') !== null) {
            throw AppendOnlyRecordMutation::projectDocumentLink();
        }
    }

    /** @param array<int|string, mixed> $values */
    private static function assertDetachValues(array $values): void
    {
        $keys = array_keys($values);
        if (! array_key_exists('detached_at', $values)
            || $values['detached_at'] === null
            || array_diff($keys, ['detached_by', 'detached_at']) !== []) {
            throw AppendOnlyRecordMutation::projectDocumentLink();
        }
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<ProjectRecord, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ProjectRecord::class, 'project_id');
    }

    /** @return BelongsTo<DocumentSeriesRecord, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeriesRecord::class, 'document_series_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function pinnedRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'pinned_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function attacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attached_by');
    }

    /** @return BelongsTo<User, $this> */
    public function detacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detached_by');
    }
}
