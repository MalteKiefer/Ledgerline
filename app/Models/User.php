<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * An authenticated user. Identity is first-party (email + password, optional TOTP
 * two-factor via Fortify); the legacy OIDC `oidc_sub` column is retained (nullable)
 * for provenance only. Privilege is a first-party `role` (admin|user). App login is
 * fully independent of the zero-knowledge vault passphrase.
 */
// `role` and `groups` are deliberately NOT fillable — `role` is the privilege
// boundary (drives the admin gate), so it is only ever set server-side, never
// mass-assigned from request input.
#[Fillable(['name', 'email', 'password', 'email_verified_at', 'avatar', 'avatar_url', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'groups' => 'array',
        ];
    }

    /** Whether the user holds the admin role. */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Legacy group membership check. Groups are now derived from the role for the
     * mobile /me contract; kept so any remaining caller keeps working.
     */
    public function inGroup(string $group): bool
    {
        return in_array($group, $this->effectiveGroups(), true);
    }

    /**
     * Groups exposed to the mobile API (/me). Derived from the first-party role so
     * the existing contract (an array of group strings) is preserved without OIDC.
     *
     * @return list<string>
     */
    public function effectiveGroups(): array
    {
        return $this->isAdmin() ? ['admin'] : [];
    }

    /**
     * All shared-vault memberships this user holds (as a member, not necessarily
     * as owner). Includes pending, active and revoked rows so callers can filter
     * by status themselves.
     */
    /** @return HasMany<SharedVaultMember, $this> */
    public function vaultMemberships(): HasMany
    {
        return $this->hasMany(SharedVaultMember::class);
    }

    /**
     * May this user manage the non-personal, workspace-wide settings? True only for
     * the admin role. (Replaces the old OIDC-group / single-user heuristic.)
     */
    public function managesGlobalSettings(): bool
    {
        return $this->isAdmin();
    }
}
