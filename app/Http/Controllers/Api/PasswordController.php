<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Mobile password-change surface.
 *
 * The web uses Fortify's UpdateUserPassword action which validates with the
 * `current_password:web` rule — that rule fails for Sanctum bearer tokens
 * because it internally calls `Auth::guard('web')->validate()`. We bypass it
 * here with a straightforward `Hash::check` which is guard-agnostic and
 * equally correct.
 */
class PasswordController extends Controller
{
    /**
     * Change the authenticated user's app password.
     *
     * Validates the current password with Hash::check (guard-agnostic — the
     * `current_password:web` rule used by Fortify's web action is incompatible
     * with Sanctum bearer tokens). The new password is subject to the same
     * Password::min(12) floor as web registration.
     *
     * Returns 422 `{errors:{current_password:["…"]}}` when the supplied current
     * password does not match.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', Password::min(12), 'confirmed'],
        ]);

        $user = $this->requireUser($request);

        if (! Hash::check($request->string('current_password')->value(), (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($request->string('password')->value()),
        ])->save();

        return response()->json(['ok' => true]);
    }
}
