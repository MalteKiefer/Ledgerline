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
        $data = $request->validate([
            'code' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:60'],
        ]);

        $pairing->claim($data['code'], $data['device_name']);

        return response()->json(['status' => 'pending']);
    }

    /** App polls with the code (public). Returns the token once the owner approves. */
    public function collect(Request $request, Pairing $pairing): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $result = $pairing->collect($data['code']);
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
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'usage' => [
                'files' => (int) FileBlob::query()->where('user_id', $user->id)->sum('size'),
                'gallery' => (int) GalleryBlob::query()->where('user_id', $user->id)->sum('size'),
            ],
        ]);
    }

    /** Revoke the presented bearer (log the device out). */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'groups' => $user->groups ?? [],
        ];
    }
}
