<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * An authenticated user, provisioned from the Pocket-ID OIDC provider.
 *
 * Users are never created with a local password; they are matched on their
 * stable OIDC subject identifier ("oidc_sub"). All authenticated users share a
 * single workspace.
 */
// `groups` is deliberately NOT fillable — it drives the admin gate, so it is
// only ever set server-side via forceFill() from the OIDC claim, never
// mass-assigned from request input.
#[Fillable(['oidc_sub', 'name', 'email', 'email_verified_at', 'avatar', 'avatar_url', 'locale'])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'groups' => 'array',
        ];
    }

    /** Whether the user belongs to the given OIDC group. */
    public function inGroup(string $group): bool
    {
        return in_array($group, $this->groups ?? [], true);
    }

    /**
     * May this user manage the non-personal, workspace-wide settings? True when
     * no admin group is configured (single-admin / backwards compatible), else
     * only members of that group.
     */
    public function managesGlobalSettings(): bool
    {
        $adminGroup = config('services.pocketid.admin_group');

        if (filled($adminGroup)) {
            return $this->inGroup((string) $adminGroup);
        }

        // No admin group configured: allow on a single-user install (backwards
        // compatible), but fail CLOSED on multi-user installs — otherwise every
        // authenticated user could reach workspace-wide settings and download
        // backups of ALL users' data. Multi-user installs must set
        // POCKETID_ADMIN_GROUP to grant admin access.
        return static::query()->count() <= 1;
    }
}
