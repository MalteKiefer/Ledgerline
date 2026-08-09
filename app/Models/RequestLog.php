<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One HTTP request (web or api), recorded after the response. Metadata only.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $ip
 * @property string $method
 * @property string $path
 * @property int $status
 * @property string|null $user_agent
 * @property string|null $referer
 * @property int|null $duration_ms
 * @property Carbon $created_at
 */
class RequestLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
