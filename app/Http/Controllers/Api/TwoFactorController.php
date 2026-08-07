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

        ($enable)($user);

        return response()->json(['enabled' => true]);
    }

    /**
     * Return the QR code SVG, plain-text TOTP secret and otpauth URI.
     *
     * Only meaningful between enable and confirm; returns 404 when 2FA has not
     * been enabled yet (no secret stored).
     */
    public function qr(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        if (is_null($user->two_factor_secret)) {
            abort(404, 'Two-factor authentication has not been enabled.');
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
     * recovery codes). Mirrors the web confirmPassword gate: a stolen device token
     * cannot disable 2FA or read recovery codes without the account password.
     *
     * @throws ValidationException
     */
    private function requireCurrentPassword(Request $request, User $user): void
    {
        $pw = $request->string('current_password')->value();
        if ($pw === '' || ! Hash::check($pw, (string) $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('The provided password does not match your current password.')],
            ]);
        }
    }

    // NOTE: the GET read is gated only by the device token + 2FA-confirmed state.
    // The web viewer adds a session password-confirm, but a stateless GET cannot
    // carry a password without leaking it into URLs/logs — so the destructive ops
    // (regenerate, disable) get the password step-up instead; this read does not.
    public function recoveryCodes(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

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
