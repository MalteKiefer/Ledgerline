<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\FortifyServiceProvider;
use App\Services\Auth\Pairing;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
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
            // Native clients (Android) send device metadata so the login registers
            // a proper "device" the same way QR pairing does. Absent = browser SPA.
            'device_name' => ['nullable', 'string', 'max:64'],
            'install_id' => ['nullable', 'string', 'max:64'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'os_version' => ['nullable', 'string', 'max:32'],
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

        // A native client identifies itself with an install_id (and a device name).
        // Register it as a real device (cap/dedup/meta) so it appears under
        // "Connected devices" with revoke/wipe/heartbeat — identical to QR pairing.
        // A browser SPA (no install_id) keeps the plain device-scoped token.
        $installId = $request->string('install_id')->value();
        if ($installId !== '') {
            $name = $request->filled('device_name') ? $request->string('device_name')->value() : 'device';
            $token = app(Pairing::class)->issueDeviceToken($user, $name, $request->ip(), [
                'install_id' => $installId,
                'app_version' => $request->string('app_version')->value() ?: null,
                'os_version' => $request->string('os_version')->value() ?: null,
            ])->plainTextToken;
        } else {
            $name = $request->filled('device_name') ? $request->string('device_name')->value() : 'web';
            $token = $user->createToken($name, ['device'])->plainTextToken;
        }

        return response()->json([
            'token' => $token,
            'user' => app(AuthController::class)->userPayload($user),
        ]);
    }

    /**
     * Request a password-reset link (public). Always responds with a generic 200 so
     * the endpoint cannot be used to enumerate which emails have an account. Mirrors
     * the web Fortify forgot-password POST.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        // Fire-and-forget: the broker only sends when the email exists, but the
        // response is identical either way (no user enumeration).
        Password::broker()->sendResetLink(['email' => $request->string('email')->value()]);

        return response()->json(['status' => 'reset-link-sent']);
    }

    /**
     * Consume a password-reset token (public). Reuses the Fortify ResetUserPassword
     * action so the logic (password rules + kill-switch that revokes every device
     * token/session) is identical to the web pipeline. Mirrors the web reset-password
     * POST. Returns 200 on success, 422 on an invalid token/email.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                app(ResetUserPassword::class)->reset($user, [
                    'password' => $request->string('password')->value(),
                    'password_confirmation' => $request->string('password_confirmation')->value(),
                ]);
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PasswordReset) {
            return response()->json(['status' => 'password-reset']);
        }

        $statusKey = is_string($status) ? $status : 'passwords.token';

        return response()->json(['status' => $statusKey, 'message' => __($statusKey)], 422);
    }

    /**
     * Self-register (public). Gated by the workspace `allow_registration` flag (403
     * when off). Reuses the Fortify CreateNewUser action — the privileged `role` is
     * never taken from input (always 'user'). Because accounts must verify their
     * email, no bearer is minted until the address is verified: responds
     * {status: 'verify-email'} and fires the verification notification. Mirrors the
     * web Fortify register POST.
     */
    public function register(Request $request): JsonResponse
    {
        if (! FortifyServiceProvider::registrationOpen()) {
            return response()->json(['message' => 'registration_disabled'], 403);
        }

        /** @var array<string, string> $input */
        $input = [
            'name' => $request->string('name')->value(),
            'email' => $request->string('email')->value(),
            'password' => $request->string('password')->value(),
            'password_confirmation' => $request->string('password_confirmation')->value(),
        ];

        $user = app(CreateNewUser::class)->create($input);

        event(new Registered($user));

        // Email verification is enabled workspace-wide; a fresh account is unverified,
        // so withhold the bearer until it verifies (the SPA shows a "check your inbox"
        // state). If verification is ever disabled, mint the token immediately.
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            return response()->json(['status' => 'verify-email'], 201);
        }

        $name = $request->filled('device_name') ? $request->string('device_name')->value() : 'web';
        $token = $user->createToken($name, ['device'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => app(AuthController::class)->userPayload($user),
        ], 201);
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
