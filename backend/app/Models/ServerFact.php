<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One collection run against a server: the parsed snapshot, or the reason the
 * run failed. It carries no user_id of its own — every read goes through the
 * owning Server, whose OwnsUserData scope already constrains access. Written
 * only by the collector job, never directly by a request.
 *
 * @property int $id
 * @property int $server_id
 * @property bool $ok
 * @property string|null $error
 * @property array<string, mixed>|null $facts
 * @property int $duration_ms
 * @property Carbon $collected_at
 */
class ServerFact extends Model
{
    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'ok' => 'boolean',
            'duration_ms' => 'integer',
            'collected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
