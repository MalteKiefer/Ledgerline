<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Exception\AppendOnlyRecordMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProjectNoteRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_project_notes';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'project_id' => 'integer',
            'supersedes_note_id' => 'integer', 'created_by' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw AppendOnlyRecordMutation::projectNote();
        });
        self::deleting(static function (): void {
            throw AppendOnlyRecordMutation::projectNote();
        });
    }

    /** @return GuardedMutationBuilder<ProjectNoteRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel(
            $query,
            self::class,
            static function (GuardedMutationBuilder $builder, string $operation): void {
                if (in_array($operation, ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'], true)) {
                    return;
                }

                throw AppendOnlyRecordMutation::projectNote();
            },
        );
    }

    /** @param Builder<self> $query */
    protected function performUpdate(Builder $query): bool
    {
        throw AppendOnlyRecordMutation::projectNote();
    }

    protected function performDeleteOnModel()
    {
        throw AppendOnlyRecordMutation::projectNote();
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

    /** @return BelongsTo<ProjectNoteRecord, $this> */
    public function supersededNote(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_note_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
