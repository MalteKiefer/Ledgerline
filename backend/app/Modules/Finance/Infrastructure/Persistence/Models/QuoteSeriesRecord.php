<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\FinancePartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class QuoteSeriesRecord extends Model
{
    use OwnsUserData;
    use SoftDeletes;

    protected $table = 'finance_quote_series';

    protected $primaryKey = 'document_series_id';

    public $incrementing = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'document_series_id' => 'integer',
            'user_id' => 'integer',
            'partner_id' => 'integer',
            'current_revision_id' => 'integer',
            'sequence_year' => 'integer',
            'sequence_number' => 'integer',
            'version' => 'integer',
            'published_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'converted_at' => 'datetime',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<DocumentSeriesRecord, $this> */
    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeriesRecord::class, 'document_series_id');
    }

    /** @return BelongsTo<FinancePartner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancePartner::class, 'partner_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'current_revision_id');
    }

    /** @return HasOne<QuoteDraftRecord, $this> */
    public function draft(): HasOne
    {
        return $this->hasOne(QuoteDraftRecord::class, 'document_series_id');
    }

    /** @return HasMany<DocumentRevisionRecord, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentRevisionRecord::class, 'document_series_id');
    }

    /** @return HasMany<QuoteOperationRecord, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(QuoteOperationRecord::class, 'document_series_id');
    }

    /** @return HasMany<QuoteDeliveryRecord, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(QuoteDeliveryRecord::class, 'document_series_id');
    }

    /** @return HasMany<QuoteConversionRecord, $this> */
    public function conversions(): HasMany
    {
        return $this->hasMany(QuoteConversionRecord::class, 'document_series_id');
    }
}
