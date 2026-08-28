<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteConversionRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_quote_conversions';

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'document_series_id' => 'integer',
            'source_revision_id' => 'integer',
            'target_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<QuoteSeriesRecord, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(QuoteSeriesRecord::class, 'document_series_id');
    }

    /** @return BelongsTo<DocumentRevisionRecord, $this> */
    public function sourceRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'source_revision_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'target_id');
    }
}
