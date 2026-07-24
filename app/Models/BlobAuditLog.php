<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * One append-only forensic entry for a blob mutation or sealed-root write (see the
 * create_blob_audit_log migration). Rows are never updated — the trail is
 * tamper-evident by construction.
 *
 * @property int $id
 * @property ?int $user_id
 * @property string $module
 * @property string $action
 * @property ?string $blob
 * @property ?int $size
 * @property ?string $sha256
 * @property ?string $source
 * @property ?string $reason
 * @property string $result
 * @property array<string, mixed>|null $meta
 * @property ?Carbon $created_at
 */
#[Fillable([
    'user_id', 'module', 'action', 'blob', 'size', 'sha256', 'source', 'reason', 'result', 'meta', 'created_at',
])]
class BlobAuditLog extends Model
{
    protected $table = 'blob_audit_log';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Blob audit entries are append-only.');
        });
    }
}
