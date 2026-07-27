<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\PurgeUserAccount;
use App\Http\Controllers\Concerns\RedirectsToSettings;
use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        return view('settings.users.index', [
            'users' => User::orderBy('id')->get(),
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

        if ($password === '' && AppSettings::current()->mail_enabled) {
            Password::broker()->sendResetLink(['email' => $email]);
        }

        return $this->savedSettings('users', 'settings.users', 'settings.users_saved');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->validated($request, creating: false, ignoreId: $user->id);

        // Never demote the last remaining admin.
        if ($user->isAdmin() && $request->string('role')->value() !== 'admin' && $this->adminCount() <= 1) {
            return back()->withErrors(['role' => __('settings.users_last_admin')]);
        }

        $user->forceFill([
            'name' => $request->string('name')->value(),
            'email' => $request->string('email')->value(),
        ] + $this->privilegedFields($request))->save();

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
        if ($user->isAdmin() && $this->adminCount() <= 1) {
            return back()->withErrors(['delete' => __('settings.users_last_admin')]);
        }

        $purge->handle($user); // crypto-shred + owned data + vault

        return $this->savedSettings('users', 'settings.users', 'settings.users_deleted');
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
            'files_quota_mb' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'gallery_quota_mb' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'max_connected_devices' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
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
            'files_quota_mb' => $limit('files_quota_mb'),
            'gallery_quota_mb' => $limit('gallery_quota_mb'),
            'max_connected_devices' => $limit('max_connected_devices'),
        ];
    }

    private function adminCount(): int
    {
        return User::where('role', 'admin')->count();
    }
}
