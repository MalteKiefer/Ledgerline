<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteDeliveryRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_quote_deliveries';

    protected $fillable = [
        'recipient',
        'recipient_domain',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'document_series_id' => 'integer',
            'document_revision_id' => 'integer',
            'attempts' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
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
    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevisionRecord::class, 'document_revision_id');
    }
}
