<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\PurgeUserAccount;
use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\Group;
use App\Models\User;
use App\Support\BlobStore;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin user management (workspace-wide, gated by manage-global-settings): list,
 * create, edit role + per-user limits, force a password reset, and delete users.
 * Roles + quota/device columns are set server-side (never mass-assigned — they are
 * the privilege/limit boundary). Last-admin and self-delete are guarded.
 */
class UsersController extends Controller
{
    use RedirectsToSettings;

    public function index(Request $request): View
    {
        $users = User::with('memberGroups')->orderBy('id')->get();

        return view('settings.users.index', [
            'users' => $users,
            'groups' => Group::orderBy('name')->get(),
            'settings' => AppSettings::current(),
            'mailEnabled' => (bool) AppSettings::current()->mail_enabled,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->validated($request, creating: true);

        $password = $request->string('password')->value();
        $email = $request->string('email')->value();

        $user = new User;
        // forceFill so role + limit columns (deliberately non-fillable) can be set
        // here (the admin CRUD is the only place that may). Admin-created accounts
        // are pre-verified; a random password forces a reset when none is provided.
        $user->forceFill([
            'name' => $request->string('name')->value(),
            'email' => $email,
            'password' => Hash::make($password !== '' ? $password : Str::random(48)),
            'email_verified_at' => now(),
        ] + $this->privilegedFields($request))->save();
        $user->memberGroups()->sync($this->groupIds($request));

        if ($password === '' && AppSettings::current()->mail_enabled) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        return $this->savedSettings('users', 'settings.users', 'settings.users_saved');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->validated($request, creating: false, ignoreId: $user->id);

        // Never demote the last remaining admin — atomic check+save (TOCTOU-safe).
        $orphaned = DB::transaction(function () use ($request, $user): bool {
            if ($user->isAdmin() && $request->string('role')->value() !== 'admin'
                && User::where('role', 'admin')->whereKeyNot($user->getKey())->lockForUpdate()->count() < 1) {
                return true;
            }
            $user->forceFill([
                'name' => $request->string('name')->value(),
                'email' => $request->string('email')->value(),
            ] + $this->privilegedFields($request))->save();
            $user->memberGroups()->sync($this->groupIds($request));

            return false;
        });
        if ($orphaned) {
            return back()->withErrors(['role' => __('settings.users_last_admin')]);
        }

        return $this->savedSettings('users', 'settings.users', 'settings.users_saved');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if (! AppSettings::current()->mail_enabled) {
            return back()->withErrors(['reset' => __('settings.users_mail_off')]);
        }
        Password::broker()->sendResetLink(['email' => $user->email]);

        return $this->savedSettings('users', 'settings.users', 'settings.users_reset_sent');
    }

    public function destroy(Request $request, User $user, PurgeUserAccount $purge): RedirectResponse
    {
        if ($user->id === $this->requireUser($request)->id) {
            return back()->withErrors(['delete' => __('settings.users_no_self_delete')]);
        }
        $orphaned = DB::transaction(function () use ($user, $purge): bool {
            if ($user->isAdmin() && User::where('role', 'admin')->whereKeyNot($user->getKey())->lockForUpdate()->count() < 1) {
                return true;
            }
            $purge->handle($user); // purge the user's owned data (plaintext-relational)

            return false;
        });
        if ($orphaned) {
            return back()->withErrors(['delete' => __('settings.users_last_admin')]);
        }

        return $this->savedSettings('users', 'settings.users', 'settings.users_deleted');
    }

    /** Clear a user's two-factor authentication (admin recovery when they lose their device). */
    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        // Admin recovery for OTHER users only — self-reset must go through the
        // step-up-protected self-service flow (mirrors the API twin).
        if ($user->id === $this->requireUser($request)->id) {
            return back()->withErrors(['reset' => __('settings.users_no_self_2fa_reset')]);
        }
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $this->savedSettings('users', 'settings.users', 'settings.users_2fa_reset_done');
    }

    /** Stream a user's avatar for the admin list (admin-gated by the route group). */
    public function avatar(Request $request, User $user): StreamedResponse
    {
        $path = $user->avatar;
        $disk = BlobStore::disk();
        abort_if(! is_string($path) || $path === '' || ! $disk->exists($path), 404);

        return $disk->response($path, 'avatar', [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'inline');
    }

    public function registration(Request $request): RedirectResponse
    {
        AppSettings::current()->update(['allow_registration' => $request->boolean('allow_registration')]);

        return $this->savedSettings('users', 'settings.users', 'settings.users_saved');
    }

    private function validated(Request $request, bool $creating, ?int $ignoreId = null): void
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($ignoreId)],
            'role' => ['required', Rule::in(['admin', 'user'])],
            'password' => [$creating ? 'nullable' : 'prohibited', 'string', 'min:12'],
            'max_connected_devices' => ['nullable', 'integer', 'min:1', 'max:50'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys((array) config('modules.list', [])))],
        ]);
    }

    /**
     * The submitted group ids (membership). Absent = no change vs cleared; the form
     * always posts the full set, so an empty array clears membership.
     *
     * @return list<int>
     */
    private function groupIds(Request $request): array
    {
        $ids = $request->input('groups', []);
        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $v): int => is_numeric($v) ? (int) $v : 0,
            $ids,
        ), static fn (int $v): bool => $v > 0));
    }

    /**
     * The privilege/limit columns (never mass-assigned; set only via this admin
     * CRUD). A blank limit clears the per-user override (falls back to the workspace
     * default).
     *
     * @return array<string, mixed>
     */
    private function privilegedFields(Request $request): array
    {
        $limit = static fn (string $key): ?int => $request->integer($key) > 0 ? $request->integer($key) : null;

        return [
            'role' => $request->string('role')->value() === 'admin' ? 'admin' : 'user',
            'max_connected_devices' => $limit('max_connected_devices'),
            'modules' => GroupsController::modulesFromRequest($request),
        ];
    }
}
