<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * One append-only security audit entry (a login, a privileged action, a
 * settings change). Rows are never updated: the trail is tamper-evident by
 * construction — mutating an existing row throws.
 */
#[Fillable([
    'user_id', 'action', 'subject_type', 'subject_id', 'ip', 'user_agent', 'meta', 'created_at',
])]
class AuditLog extends Model
{
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
        // Append-only: block any in-place mutation of an existing entry.
        static::updating(function (): void {
            throw new RuntimeException('Audit log entries are append-only.');
        });
    }

    /**
     * Record one audit entry for the current actor + request. Best-effort: an
     * audit-write failure must never break the action being audited.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, array $meta = [], ?int $userId = null): void
    {
        try {
            $request = request();
            static::create([
                'user_id' => $userId ?? Auth::id(),
                'action' => $action,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => is_scalar($subject?->getKey()) ? (string) $subject->getKey() : null,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
                'meta' => $meta !== [] ? $meta : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Swallow — auditing is observational and must not fail the operation.
        }
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
