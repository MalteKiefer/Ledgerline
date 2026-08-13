<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * One append-only, throttled device-access entry: when a device token reached
 * the API, from which IP/user-agent, for which route GROUP (coarsened — never a
 * full path with ids), and the response status. Metadata only; no secrets. Kept
 * short-term (device-access-log:prune) as a usage trail for the "connected
 * devices" history, distinct from the long-lived lifecycle audit_logs.
 */
#[Fillable([
    'token_id', 'user_id', 'ip', 'user_agent', 'route_group', 'status', 'created_at',
])]
class DeviceAccessLog extends Model
{
    protected $table = 'device_access_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'token_id' => 'integer',
            'user_id' => 'integer',
            'status' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Device access log entries are append-only.');
        });
    }
}
