<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class DocumentSeriesRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_document_series';

    protected $fillable = [
        'document_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'source_id' => 'integer',
            'created_by' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
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

    /** @return HasMany<DocumentRevisionRecord, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevisionRecord::class, 'document_series_id');
    }

    /** @return HasMany<DocumentActivityRecord, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivityRecord::class, 'document_series_id');
    }

    /** @return HasMany<DocumentNoteRecord, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(DocumentNoteRecord::class, 'document_series_id');
    }
}
