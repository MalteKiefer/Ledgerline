<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuoteOperationRecord extends Model
{
    use OwnsUserData;

    public $timestamps = false;

    protected $table = 'finance_quote_operations';

    protected $fillable = [
        'operation',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'document_series_id' => 'integer',
            'result' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
}
