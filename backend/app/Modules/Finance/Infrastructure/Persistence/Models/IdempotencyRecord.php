<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Persistence\Models;

use App\Models\Concerns\OwnsUserData;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdempotencyRecord extends Model
{
    use OwnsUserData;

    protected $table = 'finance_idempotency_records';

    protected $guarded = ['id', 'user_id', 'key_hash'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer', 'response_status' => 'integer', 'response_payload' => 'array',
            'completed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
