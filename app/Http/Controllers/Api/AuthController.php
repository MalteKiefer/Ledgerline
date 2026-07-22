<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileBlob;
use App\Models\GalleryBlob;
use App\Models\User;
use App\Services\Auth\Pairing;
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
        $request->validate(['code' => ['required', 'string']]);

        $result = $pairing->collect($request->string('code')->value(), $request->ip());
        if ($result['status'] !== 'approved') {
            return response()->json(['status' => 'pending']);
        }

        return response()->json([
            'status' => 'approved',
            'token' => $result['token'],
            'user' => $this->userPayload($result['user']),
        ]);
    }

    /** The authenticated user + storage usage (bearer). */
    public function me(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        return response()->json([
            'user' => $this->userPayload($user),
            'usage' => [
                'files' => (int) FileBlob::query()->where('user_id', $user->id)->sum('size'),
                'gallery' => (int) GalleryBlob::query()->where('user_id', $user->id)->sum('size'),
            ],
            // Kill switch: the owner asked to wipe this client from the web.
            'wipe' => $this->wipeRequested($request),
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
        $this->requireUser($request)->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'groups' => $user->groups ?? [],
            // Non-secret Pocket-ID avatar. True → fetch GET /api/v1/avatar (Bearer).
            'has_avatar' => is_string($user->avatar) && $user->avatar !== '',
        ];
    }
}
