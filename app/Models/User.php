<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * An authenticated user. Identity is first-party (email + password, optional TOTP
 * two-factor via Fortify). Privilege is a first-party `role` (admin|user).
 */
// `role` and `groups` are deliberately NOT fillable — `role` is the privilege
// boundary (drives the admin gate), so it is only ever set server-side, never
// mass-assigned from request input.
#[Fillable(['name', 'email', 'password', 'email_verified_at', 'avatar', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'webdav_password'])]
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
            'max_connected_devices' => 'integer',
            'modules' => 'array',
        ];
    }

    /**
     * The application modules this user may access. Admins get everything. Otherwise a
     * per-user allow-list wins; else the UNION of the user's groups' allow-lists (a group
     * without a list grants all — most-generous, matching the group-limits policy); else,
     * nothing configured anywhere, all modules. Unknown keys are dropped.
     *
     * @return list<string>
     */
    public function allowedModules(): array
    {
        $all = array_keys((array) config('modules.list', []));
        if ($this->isAdmin()) {
            return $all;
        }
        if (is_array($this->modules)) {
            return array_values(array_intersect($all, $this->modules));
        }
        $groups = $this->memberGroups;
        if ($groups->isEmpty()) {
            return $all;
        }
        $allowed = [];
        foreach ($groups as $group) {
            if (! is_array($group->modules)) {
                return $all; // a group without a restriction grants everything
            }
            $allowed = array_merge($allowed, $group->modules);
        }

        return array_values(array_intersect($all, array_unique($allowed)));
    }

    /** Whether the user may access a given module key. */
    public function canModule(string $key): bool
    {
        return in_array($key, $this->allowedModules(), true);
    }

    /** Effective connected-device cap: per-user override, else group, else workspace, else config. */
    public function effectiveMaxDevices(): int
    {
        if ($this->max_connected_devices !== null) {
            return $this->max_connected_devices;
        }
        $group = $this->maxGroupLimit('max_connected_devices');
        if ($group !== null) {
            return $group;
        }
        $workspace = AppSettings::current()->max_connected_devices;

        return is_numeric($workspace) && (int) $workspace > 0
            ? (int) $workspace
            : self::configInt('devices.max', 3);
    }

    /**
     * The most generous limit set by any of the user's groups for a given column,
     * or null if none of their groups sets it. "Most generous" = the highest value
     * (per the group-limits policy: joining a group only ever raises capacity).
     */
    private function maxGroupLimit(string $column): ?int
    {
        $values = $this->memberGroups
            ->pluck($column)
            ->filter(static fn ($v): bool => is_numeric($v))
            ->map(static fn ($v): int => (int) $v);

        return $values->isEmpty() ? null : (int) $values->max();
    }

    /** Groups this user belongs to (limit templates + shareable targets). */
    /** @return BelongsToMany<Group, $this> */
    public function memberGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    /** A config value read as a non-negative int (config returns mixed). */
    private static function configInt(string $key, int $default = 0): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
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
     * May this user manage the non-personal, workspace-wide settings? True only for
     * the admin role. (Replaces the old OIDC-group / single-user heuristic.)
     */
    public function managesGlobalSettings(): bool
    {
        return $this->isAdmin();
    }
}
