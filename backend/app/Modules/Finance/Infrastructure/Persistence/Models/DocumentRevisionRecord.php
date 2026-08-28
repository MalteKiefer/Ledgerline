<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use App\Modules\Finance\Infrastructure\Persistence\Exception\PublishedRevisionMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocumentRevisionRecord extends Model
{
    use OwnsUserData;

    /** @var list<string> */
    private const array PUBLICATION_FIELDS = [
        'status',
        'pdf_path',
        'pdf_sha256',
        'published_at',
    ];

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
        self::creating(function (self $revision): void {
            $revision->assertDraftCreation();
        });
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
            static function (GuardedMutationBuilder $builder, string $operation, array $values): void {
                if (in_array(
                    $operation,
                    ['insert', 'insertOrIgnore', 'insertOrIgnoreReturning', 'insertGetId'],
                    true,
                )) {
                    self::assertDraftInsertValues($values);

                    return;
                }

                if (in_array(
                    $operation,
                    ['insertUsing', 'insertOrIgnoreUsing', 'upsert', 'updateOrInsert', 'truncate'],
                    true,
                )) {
                    throw PublishedRevisionMutation::revision();
                }

                if (array_intersect(self::PUBLICATION_FIELDS, array_keys($values)) !== []) {
                    throw PublishedRevisionMutation::revision();
                }

                $published = clone $builder;
                $matched = $published->lockForUpdate()->get(['published_at']);

                if ($matched->contains(
                    static fn (self $revision): bool => $revision->getRawOriginal('published_at') !== null,
                )) {
                    throw PublishedRevisionMutation::revision();
                }

                $builder->whereNull($builder->getModel()->qualifyColumn('published_at'));
            },
        );
    }

    /** @param Builder<self> $query */
    protected function performInsert($query): bool
    {
        $this->assertDraftCreation();

        return parent::performInsert($query);
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<int, string>|string|null  $uniqueBy
     */
    protected function performInsertOrIgnore($query, array|string|null $uniqueBy): bool
    {
        $this->assertDraftCreation();

        return parent::performInsertOrIgnore($query, $uniqueBy);
    }

    /** @param array<int|string, mixed> $values */
    private static function assertDraftInsertValues(array $values): void
    {
        $first = reset($values);
        $rows = is_array($first) ? $values : [$values];

        foreach ($rows as $row) {
            if (! is_array($row)
                || ($row['status'] ?? null) !== 'draft'
                || ($row['pdf_path'] ?? null) !== null
                || ($row['pdf_sha256'] ?? null) !== null
                || ($row['published_at'] ?? null) !== null) {
                throw PublishedRevisionMutation::revision();
            }
        }
    }

    private function assertMutable(): void
    {
        if ($this->getRawOriginal('published_at') !== null
            || $this->isDirty(self::PUBLICATION_FIELDS)) {
            throw PublishedRevisionMutation::revision();
        }
    }

    private function assertDraftCreation(): void
    {
        if ($this->getAttribute('status') !== 'draft'
            || $this->getAttribute('pdf_path') !== null
            || $this->getAttribute('pdf_sha256') !== null
            || $this->getAttribute('published_at') !== null) {
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
