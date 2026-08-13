<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AppSettings;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the workspace enforces 2FA (app_settings.force_2fa), a user without a
 * confirmed second factor may only reach the endpoints needed to enrol (2FA
 * setup), read their profile, or log out. Everything else returns 403
 * {status:'two_factor_required'} so the SPA can redirect to the setup screen.
 */
class EnsureTwoFactorEnrolled
{
    /** Path suffixes (after api/v1/) always reachable so a user can enrol / escape. */
    private const ALLOW = [
        'me', 'auth/logout', 'preferences', 'locale', 'theme',
    ];

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->two_factor_confirmed_at !== null) {
            return $next($request);
        }
        if (! AppSettings::current()->force_2fa) {
            return $next($request);
        }
        // Allow the enrolment + escape endpoints.
        $path = ltrim((string) $request->path(), '/');
        $path = preg_replace('#^api/v1/#', '', $path) ?? $path;
        if (str_starts_with($path, 'user/two-factor') || in_array($path, self::ALLOW, true)) {
            return $next($request);
        }

        return response()->json([
            'status' => 'two_factor_required',
            'message' => 'Two-factor authentication is required by your administrator.',
        ], 403);
    }
}
