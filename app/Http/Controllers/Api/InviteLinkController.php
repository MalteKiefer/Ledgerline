<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InviteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Public (unauthenticated) consumption of a mail-independent invite / password-reset
 * link over the API. The admin CREATE side is Api\UsersController@inviteLink; this is
 * the tokenless consume side for the SPA / mobile: `show` reports validity as JSON
 * (never a redirect, unlike the web controller which renders a Blade form), and
 * `store` sets the password then MINTS A BEARER (rather than a session login). The
 * hashed, single-use, expiring token is checked in constant time via the model.
 */
class InviteLinkController extends Controller
{
    /** Public: report whether the token is valid (JSON, no view/redirect). */
    public function show(Request $request, InviteLink $invite, string $token): JsonResponse
    {
        $user = $invite->user;
        if ($user === null || ! $invite->matches($token) || ! $invite->isValid()) {
            return response()->json(['valid' => false], 404);
        }

        return response()->json([
            'valid' => true,
            'email' => $user->email,
            'expiresAt' => $invite->expires_at?->toIso8601String(),
        ]);
    }

    /** Public: consume the link — set the password, return a device-scoped bearer. */
    public function store(Request $request, InviteLink $invite, string $token): JsonResponse
    {
        $user = $invite->user;
        if ($user === null || ! $invite->matches($token) || ! $invite->isValid()) {
            return response()->json(['valid' => false], 404);
        }

        $request->validate([
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:64'],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->string('password')->value()),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $invite->forceFill(['used_at' => now()])->save();

        AuditLog::record('user.invite_link_used', null, ['user_id' => $user->id, 'via' => 'api']);

        $name = $request->filled('device_name') ? $request->string('device_name')->value() : 'web';
        $bearer = $user->createToken($name, ['device'])->plainTextToken;

        return response()->json([
            'token' => $bearer,
            'user' => app(AuthController::class)->userPayload($user),
        ]);
    }
}
