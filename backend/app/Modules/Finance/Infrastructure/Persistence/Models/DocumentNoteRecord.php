<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Exception\PublishedRevisionMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentNoteRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_document_notes';

    protected $fillable = [
        'document_revision_id',
        'type',
        'visibility',
        'body',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'document_series_id' => 'integer',
            'document_revision_id' => 'integer',
            'supersedes_note_id' => 'integer',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw PublishedRevisionMutation::note();
        });
        self::deleting(static function (): void {
            throw PublishedRevisionMutation::note();
        });
    }

    /** @return GuardedMutationBuilder<DocumentNoteRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel(
            $query,
            self::class,
            static function (GuardedMutationBuilder $builder, string $operation): void {
                if (in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                    return;
                }

                throw PublishedRevisionMutation::note();
            },
        );
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw PublishedRevisionMutation::note();
    }

    protected function performDeleteOnModel()
    {
        throw PublishedRevisionMutation::note();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<DocumentSeriesRecord, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeriesRecord::class, 'document_series_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'document_revision_id');
    }

    /** @return BelongsTo<DocumentNoteRecord, $this> */
    public function supersededNote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_note_id');
    }
}
