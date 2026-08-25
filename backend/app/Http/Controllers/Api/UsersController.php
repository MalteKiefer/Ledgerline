<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\PurgeUserAccount;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\GroupsController;
use App\Models\AppSettings;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\InviteLink;
use App\Models\User;
use App\Notifications\InviteLinkNotification;
use App\Support\BlobStore;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin user management over the API (Sanctum device token + manage-global-settings).
 * JSON mirror of the web Settings/UsersController. Role + quota columns are the
 * privilege/limit boundary — set only via forceFill/privilegedFields, never
 * mass-assigned. Last-admin and self-delete guards are preserved exactly as in the
 * web controller.
 */
class UsersController extends Controller
{
    /**
     * List all users with storage usage and effective limits.
     *
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with('memberGroups')->orderBy('id')->get();

        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        foreach ($users as $u) {
            $loginAt = $u->last_login_at;

            /** @var list<array{id: int, name: string}> $groups */
            $groups = [];
            foreach ($u->memberGroups as $g) {
                $groups[] = ['id' => $g->id, 'name' => $g->name];
            }

            $rows[] = [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role,
                'max_connected_devices' => $u->max_connected_devices,
                'modules' => $u->modules,  // null = all; else the per-user allow-list
                'groups' => $groups,
                'verified' => $u->email_verified_at !== null,
                'two_factor' => $u->two_factor_confirmed_at !== null,
                'last_login_at' => $loginAt instanceof Carbon ? $loginAt->toIso8601String() : null,
                // Blocking is consequential and was visible nowhere but in the answer
                // to the block itself: going through accounts after an incident,
                // nothing said which ones had already been handled. A count on the
                // dashboard says four are blocked, never which four.
                'blocked_at' => $u->blocked_at instanceof Carbon ? $u->blocked_at->toIso8601String() : null,
            ];
        }

        return response()->json(['users' => $rows]);
    }

    /**
     * Create a new user.
     *
     * POST /api/v1/users   throttle:30,1
     */
    public function store(Request $request): JsonResponse
    {
        $this->validated($request, creating: true);

        $password = $request->string('password')->value();
        $email = $request->string('email')->value();

        $user = new User;
        // forceFill: role + limit columns are not fillable (privilege boundary).
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

        return response()->json(['user' => $this->present($user->load('memberGroups'))], 201);
    }

    /**
     * Update an existing user.
     *
     * PUT /api/v1/users/{user}   throttle:60,1
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->validated($request, creating: false, ignoreId: $user->id);

        // Never demote the last remaining admin. Check + save atomically under a
        // row lock so two concurrent demote/delete requests can't both pass the
        // count check and leave zero admins (TOCTOU).
        return DB::transaction(function () use ($request, $user): JsonResponse {
            if ($user->isAdmin()
                && $request->string('role')->value() !== 'admin'
                && $this->otherAdminsLocked($user) < 1
            ) {
                return response()->json(['errors' => ['role' => [__('settings.users_last_admin')]]], 422);
            }

            $user->forceFill([
                'name' => $request->string('name')->value(),
                'email' => $request->string('email')->value(),
            ] + $this->privilegedFields($request))->save();
            $user->memberGroups()->sync($this->groupIds($request));

            return response()->json(['user' => $this->present($user->load('memberGroups'))]);
        });
    }

    /**
     * Delete a user (GDPR crypto-shred + all owned data).
     *
     * DELETE /api/v1/users/{user}   throttle:10,1
     */
    public function destroy(Request $request, User $user, PurgeUserAccount $purge): JsonResponse
    {
        $caller = $this->requireUser($request);

        if ($user->id === $caller->id) {
            return response()->json(['errors' => ['delete' => [__('settings.users_no_self_delete')]]], 422);
        }

        return DB::transaction(function () use ($user, $purge): JsonResponse {
            if ($user->isAdmin() && $this->otherAdminsLocked($user) < 1) {
                return response()->json(['errors' => ['delete' => [__('settings.users_last_admin')]]], 422);
            }
            $purge->handle($user);

            return response()->json([], 204);
        });
    }

    /**
     * Send a Fortify password-reset email for a user.
     *
     * POST /api/v1/users/{user}/reset-password
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        if (! AppSettings::current()->mail_enabled) {
            return response()->json(['errors' => ['reset' => [__('settings.users_mail_off')]]], 422);
        }

        Password::broker()->sendResetLink(['email' => $user->email]);

        return response()->json(['message' => 'reset_sent']);
    }

    /**
     * Clear two-factor authentication for a user (admin recovery).
     *
     * POST /api/v1/users/{user}/reset-2fa
     */
    public function resetTwoFactor(Request $request, User $user): JsonResponse
    {
        // This is an admin-RECOVERY path for OTHER users. An admin must not strip
        // their OWN second factor here — that would bypass the current_password
        // step-up enforced on the self-service DELETE /api/v1/user/two-factor, so a
        // stolen (non-native) admin bearer could disable 2FA without the password.
        if ($user->id === $this->requireUser($request)->id) {
            return response()->json(['errors' => ['reset' => [__('settings.users_no_self_2fa_reset')]]], 422);
        }
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['message' => '2fa_reset']);
    }

    /**
     * Stream a user's avatar (admin-gated by the route group); 404 if none stored.
     *
     * GET /api/v1/users/{user}/avatar
     */
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

    /**
     * Generate an invite / password-reset link for a user.
     * The plaintext token is returned in `url` once — it is never stored/logged.
     *
     * POST /api/v1/users/{user}/invite-link   throttle:20,1
     */
    public function inviteLink(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'ttl_hours' => ['required', 'integer', 'in:'.implode(',', InviteLink::TTL_HOURS)],
            'send' => ['nullable', 'boolean'],
        ]);

        $token = InviteLink::newToken();
        $link = new InviteLink;
        $link->forceFill([
            'user_id' => $user->id,
            'token_hash' => InviteLink::hashToken($token),
            'expires_at' => now()->addHours($request->integer('ttl_hours')),
            'created_by' => $this->requireUser($request)->id,
        ])->save();

        $url = route('invite.show', ['invite' => $link->id, 'token' => $token]);

        AuditLog::record('user.invite_link_created', null, [
            'user_id' => $user->id,
            'ttl_hours' => $request->integer('ttl_hours'),
            'emailed' => $request->boolean('send') && AppSettings::current()->mail_enabled,
        ]);

        $sent = false;
        if ($request->boolean('send') && AppSettings::current()->mail_enabled) {
            $user->notify(new InviteLinkNotification($url, $link->expires_at));
            $sent = true;
        }

        return response()->json([
            'url' => $url,
            'expires_at' => $link->expires_at?->toIso8601String(),
            'sent' => $sent,
        ]);
    }

    /**
     * Read the workspace self-registration toggle.
     *
     * GET /api/v1/admin/registration
     */
    public function registrationShow(Request $request): JsonResponse
    {
        return response()->json(['allow_registration' => (bool) AppSettings::current()->allow_registration]);
    }

    /**
     * Set the workspace self-registration toggle. Mirrors the web
     * Settings/UsersController@registration.
     *
     * PUT /api/v1/admin/registration   throttle:60,1
     */
    public function registration(Request $request): JsonResponse
    {
        $request->validate(['allow_registration' => ['required', 'boolean']]);

        AppSettings::current()->update(['allow_registration' => $request->boolean('allow_registration')]);

        return response()->json(['allow_registration' => (bool) AppSettings::current()->allow_registration]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Present a user for API responses (no credentials — only metadata).
     *
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        /** @var Collection<int, Group> $groups */
        $groups = $user->memberGroups ?? collect();

        /** @var list<array{id: int, name: string}> $groupList */
        $groupList = [];
        foreach ($groups as $g) {
            $groupList[] = ['id' => $g->id, 'name' => $g->name];
        }

        $loginAt = $user->last_login_at;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'max_connected_devices' => $user->max_connected_devices,
            'modules' => $user->modules,
            'groups' => $groupList,
            'verified' => $user->email_verified_at !== null,
            'two_factor' => $user->two_factor_confirmed_at !== null,
            'last_login_at' => $loginAt instanceof Carbon ? $loginAt->toIso8601String() : null,
        ];
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
     * The privilege/limit columns — never mass-assigned; set only via this admin API.
     * Mirrors Settings\UsersController::privilegedFields() exactly.
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

    /** @return list<int> */
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

    /** Count OTHER admins under a row lock (call inside a transaction — TOCTOU-safe). */
    private function otherAdminsLocked(User $user): int
    {
        return User::where('role', 'admin')->whereKeyNot($user->getKey())->lockForUpdate()->count();
    }
}
