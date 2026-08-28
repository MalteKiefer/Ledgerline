<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Exception\PublishedRevisionMutation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocumentRevisionRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_document_revisions';

    protected $fillable = [
        'previous_revision_id',
        'status',
        'snapshot',
        'net_minor',
        'vat_minor',
        'gross_minor',
        'currency',
        'change_reason',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'document_series_id' => 'integer',
            'revision_number' => 'integer',
            'previous_revision_id' => 'integer',
            'snapshot' => 'array',
            'net_minor' => 'integer',
            'vat_minor' => 'integer',
            'gross_minor' => 'integer',
            'published_at' => 'datetime',
            'created_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (self $revision): void {
            $revision->assertMutable();
        });
        self::deleting(function (self $revision): void {
            $revision->assertMutable();
        });
    }

    /** @return GuardedMutationBuilder<DocumentRevisionRecord> */
    public function newEloquentBuilder($query): GuardedMutationBuilder
    {
        return GuardedMutationBuilder::forModel(
            $query,
            self::class,
            static function (GuardedMutationBuilder $builder, string $operation): void {
                if (in_array($operation, ['upsert', 'updateOrInsert', 'truncate'], true)) {
                    throw PublishedRevisionMutation::revision();
                }

                $published = clone $builder;

                if ($published->whereNotNull('published_at')->exists()) {
                    throw PublishedRevisionMutation::revision();
                }
            },
        );
    }

    private function assertMutable(): void
    {
        if ($this->getRawOriginal('published_at') !== null) {
            throw PublishedRevisionMutation::revision();
        }
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
    public function previousRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_revision_id');
    }

    /** @return HasMany<DocumentRevisionRecord, $this> */
    public function laterRevisions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_revision_id');
    }

    /** @return HasMany<DocumentActivityRecord, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivityRecord::class, 'document_revision_id');
    }

    /** @return HasMany<DocumentNoteRecord, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(DocumentNoteRecord::class, 'document_revision_id');
    }
}
