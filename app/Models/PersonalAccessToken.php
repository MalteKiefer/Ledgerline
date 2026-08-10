<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * App Sanctum token model. Adds the per-device UnifiedPush endpoint as an
 * encrypted attribute (AES-256-GCM under APP_KEY, not in a DB dump) on top of
 * Sanctum's default columns. Registered via Sanctum::usePersonalAccessTokenModel
 * so token resolution + $user->tokens() return this subclass.
 *
 * @property ?string $push_endpoint
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Merged with Sanctum's own casts (abilities/expires_at/last_used_at) — the
     * method return is combined with the parent's $casts property by Eloquent.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'push_endpoint' => 'encrypted',
        ];
    }
}
