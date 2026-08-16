<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSettings;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\Auth\Pairing;
use App\Support\DeviceAudit;
use App\Support\StorageUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mobile auth: the app never does OIDC. It scans a QR from the web profile,
 * claims the one-time code here, and — once the owner approves the device in the
 * web UI — collects a first-party Sanctum bearer exactly once. Thereafter the
 * bearer authenticates the /api/v1 data endpoints. Everything stays zero-
 * knowledge: the token proves identity only and never unlocks a vault.
 */
class AuthController extends Controller
{
    /** App claims a scanned code (public). Moves the pairing to pending-approval. */
    public function pair(Request $request, Pairing $pairing): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:60'],
        ]);

        $pairing->claim($request->string('code')->value(), $request->string('device_name')->value());

        return response()->json(['status' => 'pending']);
    }

    /** App polls with the code (public). Returns the token once the owner approves. */
    public function collect(Request $request, Pairing $pairing): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            // Non-secret client-correlation fields (all optional).
            'install_id' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'os_version' => ['nullable', 'string', 'max:32'],
        ]);

        $result = $pairing->collect($request->string('code')->value(), $request->ip(), [
            'install_id' => $request->string('install_id')->value() ?: null,
            'app_version' => $request->string('app_version')->value() ?: null,
            'os_version' => $request->string('os_version')->value() ?: null,
        ]);
        if ($result['status'] !== 'approved') {
            return response()->json(['status' => 'pending']);
        }

        return response()->json([
            'status' => 'approved',
            'token' => $result['token'],
            'user' => $this->userPayload($result['user']),
        ]);
    }

    /** The authenticated user (bearer). */
    public function me(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        return response()->json([
            'user' => $this->userPayload($user),
            // Kill switch: the owner asked to wipe this client from the web.
            'wipe' => $this->wipeRequested($request),
            // Combined Files+Gallery storage usage (the one shared workspace-wide
            // quota — see App\Support\StorageUsage). Not folded into userPayload():
            // that method also feeds the (much more frequent) device-pairing
            // collect() response, where a DB-heavy usage query on every poll would
            // be wasted work.
            'usage' => StorageUsage::snapshotForUser((int) $user->id),
        ]);
    }

    /** Whether the presented token has been flagged for a remote wipe. */
    private function wipeRequested(Request $request): bool
    {
        $token = $this->requireUser($request)->currentAccessToken();

        return $token instanceof PersonalAccessToken && $token->wipe_requested_at !== null;
    }

    /**
     * Sync-activity heartbeat from a CLI client: record whether it is currently
     * syncing (and a short human detail) so the web can show live activity.
     * Returns the wipe flag so any heartbeat also delivers the kill switch.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'state' => ['required', 'in:idle,syncing'],
            'detail' => ['nullable', 'string', 'max:160'],
        ]);

        $token = $this->requireUser($request)->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return response()->json(['wipe' => false]);
        }
        $detail = $request->input('detail');
        $token->forceFill([
            'sync_state' => $request->string('state')->value(),
            'sync_detail' => is_string($detail) ? $detail : null,
            'sync_reported_at' => now(),
        ])->save();

        return response()->json(['wipe' => $token->wipe_requested_at !== null]);
    }

    /** Revoke the presented bearer (log the device out). */
    public function destroy(Request $request): JsonResponse
    {
        $token = $this->requireUser($request)->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            DeviceAudit::record($token, 'device.revoked', ['reason' => 'logout', 'via' => 'api']);
        }
        $token->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * The public /me user payload. Public so the SPA token-login controller can
     * return the same shape on login.
     *
     * @return array<string, mixed>
     */
    public function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            // Derived from the first-party role (admin → ['admin'], else []); keeps
            // the historical mobile contract stable without a separate 'role' field.
            'groups' => $user->effectiveGroups(),
            // The application modules this account may use (per-user/per-group toggles).
            // A native client should hide the tabs for modules NOT listed here; the API
            // also enforces it (disabled module store endpoints return 403).
            'modules' => $user->allowedModules(),
            // Non-secret avatar. True → fetch GET /api/v1/avatar (Bearer).
            'has_avatar' => is_string($user->avatar) && $user->avatar !== '',
            // Whether TOTP 2FA is confirmed — lets the SPA show the correct 2FA state.
            'two_factor' => $user->two_factor_confirmed_at !== null,
            // Workspace enforces 2FA and this user hasn't enrolled → the SPA
            // redirects to the 2FA setup screen.
            'two_factor_required' => $user->two_factor_confirmed_at === null && (bool) AppSettings::current()->force_2fa,
            // Non-secret display preferences (units + 12/24h clock). Mobile applies
            // these to its own rendering; set via POST /api/v1/preferences.
            'preferences' => UserSetting::for((int) $user->id)->displayPrefs(),
            // Current UI theme (settable via POST /api/v1/theme) so a client can
            // read back what it set. light|dark|system.
            'theme' => (string) (UserSetting::for((int) $user->id)->theme ?? 'system'),
        ];
    }
}
