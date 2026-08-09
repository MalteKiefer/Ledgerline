<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Mobile 2FA management surface.
 *
 * All routes require Sanctum bearer with abilities:device. The Fortify action
 * classes are resolved from the container and invoked directly — no TOTP logic
 * is reimplemented here.
 *
 * Confirmations: config/fortify.php has `confirm => true` but
 * `confirmPassword => false`, so no password re-confirmation is required before
 * enabling. The client must still confirm the secret with a live TOTP code
 * (POST /user/two-factor/confirm) before 2FA is considered active.
 */
class TwoFactorController extends Controller
{
    /**
     * Enable 2FA: generate a new TOTP secret + 8 recovery codes.
     *
     * Idempotent — calling again while a pending (unconfirmed) secret already
     * exists is a no-op (Fortify skips when `two_factor_secret` is present).
     */
    public function enable(Request $request, EnableTwoFactorAuthentication $enable): JsonResponse
    {
        $user = $this->requireUser($request);
        // Step-up: match the web contract (Fortify confirmPassword=true). Without
        // it a stolen device token on a 2FA-less account could bind 2FA to the
        // attacker's authenticator (lockout/persistence) with no password.
        $this->requireCurrentPassword($request, $user);

        ($enable)($user);

        return response()->json(['enabled' => true]);
    }

    /**
     * Return the QR code SVG, plain-text TOTP secret and otpauth URI.
     *
     * ENROLLMENT-ONLY: available only in the window between enable and confirm.
     * Once 2FA is confirmed the secret is never handed out again — otherwise a
     * stolen device token could read the live TOTP secret and reproduce the second
     * factor on another device (surviving a token revoke). 404 when not enabled,
     * or already confirmed.
     */
    public function qr(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        if (is_null($user->two_factor_secret) || ! is_null($user->two_factor_confirmed_at)) {
            abort(404, 'Two-factor authentication QR is only available during setup.');
        }

        $secret = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret);

        return response()->json([
            'svg' => $user->twoFactorQrCodeSvg(),
            'secret' => $secret,
            'uri' => $user->twoFactorQrCodeUrl(),
        ]);
    }

    /**
     * Confirm 2FA with a live TOTP code.
     *
     * Returns 422 with `{errors:{code:[…]}}` when the code is wrong or expired.
     * On success the account is fully 2FA-protected (`two_factor_confirmed_at`
     * is set) and recovery codes become available.
     */
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $this->requireUser($request);

        // ConfirmTwoFactorAuthentication throws ValidationException on bad code.
        ($confirm)($user, $request->string('code')->value());

        return response()->json(['confirmed' => true]);
    }

    /**
     * Return the current recovery codes (decrypted).
     *
     * Only available after 2FA has been confirmed. Returns 404 if 2FA is not
     * fully set up.
     */
    /**
     * Password step-up for sensitive 2FA operations (disable / view or regenerate
     * recovery codes). Mirrors the web confirmPassword gate: a stolen web token or
     * session cannot disable 2FA or read recovery codes without the account password.
     *
     * EXCEPTION — a NATIVE device token (a PersonalAccessToken carrying a non-empty
     * install_id, minted only by QR pairing / the native mobile login) is skipped:
     * that token is AES-256-GCM-sealed in the device keystore behind a per-use
     * biometric/PIN unlock, so every native call already required a fresh biometric
     * step-up — a re-typed login password on the device is redundant and there is no
     * natural place to prompt for it. The web SPA bearer (no install_id, kept in
     * localStorage) and web sessions get NO bypass and still require the password.
     *
     * @throws ValidationException
     */
    private function requireCurrentPassword(Request $request, User $user): void
    {
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof PersonalAccessToken && is_string($token->install_id) && $token->install_id !== '') {
            return; // native, biometric-sealed device — biometric unlock is the step-up
        }

        $pw = $request->string('current_password')->value();
        if ($pw === '' || ! Hash::check($pw, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ]);
        }
    }

    // Reading the recovery codes exposes a standing second factor, so it takes the
    // same password step-up as regenerate/disable. The password travels in the JSON
    // body (NOT a query string), so it does not leak into URLs/access logs.
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $this->requireCurrentPassword($request, $user);

        if (is_null($user->two_factor_secret) || is_null($user->two_factor_confirmed_at)) {
            abort(404, 'Two-factor authentication is not active.');
        }

        $decrypted = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_recovery_codes);
        /** @var array<int, string> $codes */
        $codes = json_decode(is_string($decrypted) ? $decrypted : '', true);

        return response()->json(['recovery_codes' => $codes]);
    }

    /**
     * Regenerate recovery codes and return the fresh set.
     */
    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): JsonResponse
    {
        $user = $this->requireUser($request);
        $this->requireCurrentPassword($request, $user);

        ($generate)($user);

        $decrypted = Fortify::currentEncrypter()->decrypt((string) $user->two_factor_recovery_codes);
        /** @var array<int, string> $codes */
        $codes = json_decode(is_string($decrypted) ? $decrypted : '', true);

        return response()->json(['recovery_codes' => $codes]);
    }

    /**
     * Disable 2FA entirely (clears secret, recovery codes and confirmed_at).
     */
    public function disable(Request $request, DisableTwoFactorAuthentication $disable): JsonResponse
    {
        $user = $this->requireUser($request);
        $this->requireCurrentPassword($request, $user);

        ($disable)($user);

        return response()->json(['enabled' => false]);
    }

    /**
     * Re-send the e-mail verification notification.
     *
     * No-op (returns ok:true) when the account is already verified — callers
     * need not check first. Throttle on the route keeps this safe.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['ok' => true]);
    }
}
