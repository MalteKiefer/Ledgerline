<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A short-lived QR device-pairing session (see the migration). Its lifecycle is
 * a small state machine driven by App\Services\Auth\Pairing.
 *
 * @property Carbon|null $expires_at
 */
#[Fillable(['user_id', 'code_hash', 'device_name', 'status', 'token_id', 'expires_at'])]
class DevicePairing extends Model
{
    use HasFactory;

    public const PENDING_SCAN = 'pending_scan';       // created, waiting for the app to scan

    public const PENDING_APPROVAL = 'pending_approval'; // app claimed, waiting for web approval

    public const APPROVED = 'approved';               // owner approved, token not yet collected

    public const CONSUMED = 'consumed';               // token delivered to the app (terminal)

    public const REJECTED = 'rejected';               // owner declined (terminal)

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
