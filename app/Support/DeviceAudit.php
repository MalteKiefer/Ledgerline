<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Records a device-token lifecycle event to the audit log with a consistent,
 * secret-free meta shape. Every path that destroys or expires a token routes
 * through here so no device can vanish without exactly one audit entry —
 * regardless of whether the trigger is a request (cap eviction, wipe enforce)
 * or a scheduled command (idle prune, wipe finalize, expiry).
 *
 * ZERO-KNOWLEDGE: the token VALUE is never touched — only its id, name and
 * timestamps (all non-secret metadata) plus the caller's reason fields.
 */
final class DeviceAudit
{
    /**
     * @param  array<string, mixed>  $extra  reason-code + event-specific fields
     */
    public static function record(PersonalAccessToken $token, string $action, array $extra = []): void
    {
        AuditLog::record($action, null, array_merge([
            'token_id' => $token->getKey(),
            'token_name' => $token->name,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
        ], $extra), is_numeric($token->tokenable_id) ? (int) $token->tokenable_id : null);
    }
}
