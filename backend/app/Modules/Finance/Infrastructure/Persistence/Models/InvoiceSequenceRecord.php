<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InvoiceSequenceRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_invoice_sequences';

    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'year' => 'integer', 'next_sequence' => 'integer',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
