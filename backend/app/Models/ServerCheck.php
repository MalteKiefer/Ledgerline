<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One reachability probe: an ICMP echo, or a TCP connect to one port.
 *
 * Append-only. Nothing updates a row — an outage is a sequence of failures, and
 * rewriting history would make the latency chart a lie. Pruned by age instead
 * (`servers:prune-checks`), because a check every few minutes adds up.
 *
 * No user_id: reachable only through the server, which is owner-scoped. Listed
 * in the owner-scope guard's SCOPED_ELSEWHERE for that reason.
 *
 * @property int $id
 * @property int $server_id
 * @property string $kind
 * @property int|null $port
 * @property bool $ok
 * @property int|null $latency_ms
 * @property string|null $error
 * @property Carbon $created_at
 */
class ServerCheck extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['server_id', 'kind', 'port', 'ok', 'latency_ms', 'error'];

    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'port' => 'integer',
            'latency_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
