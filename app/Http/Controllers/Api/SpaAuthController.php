<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Backend-agnostic browser login: email + password (+ optional TOTP/recovery
 * code) → a Sanctum bearer token. Deliberately token-based (no session cookie)
 * so the SPA is portable to a future non-Laravel (Go) API — the frontend only
 * ever sends `Authorization: Bearer <token>`. Rate-limiting is app-wide off
 * (private LAN); credentials are still Argon2id-checked and 2FA is enforced.
 */
class SpaAuthController extends Controller
{
    /**
     * Issue a device-scoped bearer token for valid credentials. When the account
     * has confirmed 2FA and no valid code was supplied, responds 422 with
     * {two_factor: true} so the client can prompt for a code and retry.
     */
    public function login(Request $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'device_name' => ['nullable', 'string', 'max:64'],
        ]);

        $email = $request->string('email')->value();
        $password = $request->string('password')->value();

        $user = User::where('email', $email)->first();
        if (! $user instanceof User || ! is_string($user->password) || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            if (! $this->passesTwoFactor($user, $twoFactor, $request->string('code')->value(), $request->string('recovery_code')->value())) {
                return response()->json(['two_factor' => true], 422);
            }
        }

        $name = $request->filled('device_name') ? $request->string('device_name')->value() : 'web';
        $token = $user->createToken($name, ['device'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => app(AuthController::class)->userPayload($user),
        ]);
    }

    /** Revoke the token making this request. */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    /** Verify a TOTP code or consume a one-time recovery code. */
    private function passesTwoFactor(User $user, TwoFactorAuthenticationProvider $twoFactor, string $code, string $recovery): bool
    {
        if ($code !== '') {
            $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

            return is_string($secret) && $twoFactor->verify($secret, $code);
        }

        if ($recovery !== '') {
            $raw = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_recovery_codes);
            $decoded = json_decode(is_string($raw) ? $raw : '[]', true);
            /** @var Collection<int, string> $codes */
            $codes = collect(is_array($decoded) ? $decoded : []);
            if ($codes->contains($recovery)) {
                // Consume the used recovery code (keep the rest).
                $remaining = $codes->reject(fn (string $c): bool => $c === $recovery)->values()->all();
                $user->forceFill([
                    'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt((string) json_encode($remaining)),
                ])->save();

                return true;
            }
        }

        return false;
    }
}
