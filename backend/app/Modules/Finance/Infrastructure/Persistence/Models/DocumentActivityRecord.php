<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Exception\PublishedRevisionMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DocumentActivityRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_document_activities';

    protected $fillable = [
        'document_revision_id',
        'type',
        'payload',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'document_series_id' => 'integer',
            'document_revision_id' => 'integer',
            'payload' => 'array',
            'created_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw PublishedRevisionMutation::activity();
        });
        self::deleting(function (): void {
            throw PublishedRevisionMutation::activity();
        });
    }

    /** @return GuardedMutationBuilder<DocumentActivityRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel(
            $query,
            self::class,
            static function (): void {
                throw PublishedRevisionMutation::activity();
            },
        );
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
}
