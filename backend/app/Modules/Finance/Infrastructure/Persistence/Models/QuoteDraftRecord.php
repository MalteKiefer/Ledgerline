<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteDraftRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_quote_drafts';

    protected $primaryKey = 'document_series_id';

    public $incrementing = false;

    protected $fillable = [
        'payload',
        'net_minor',
        'vat_minor',
        'gross_minor',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'document_series_id' => 'integer',
            'user_id' => 'integer',
            'based_on_revision_id' => 'integer',
            'payload' => 'array',
            'net_minor' => 'integer',
            'vat_minor' => 'integer',
            'gross_minor' => 'integer',
            'updated_by' => 'integer',
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
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<QuoteSeriesRecord, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(QuoteSeriesRecord::class, 'document_series_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function basedOnRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'based_on_revision_id');
    }
}
