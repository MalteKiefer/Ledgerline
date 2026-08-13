<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from, in order: the signed-in user's preference,
 * a session override, the browser's Accept-Language, then the app default.
 */
class SetLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $languages = config('locales.languages');
        $supported = is_array($languages) ? array_map('strval', array_keys($languages)) : [];

        $default = config('app.locale');
        $default = is_string($default) ? $default : 'en';

        $browser = $this->fromBrowser($request, $supported);
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $browser
            ?? $default;

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        // Persist the detected browser language onto the user once, so background
        // work (queue jobs, scheduled notifications) can render in the user's
        // language outside a request.
        $user = $request->user();
        if ($user !== null && $user->locale === null && $browser !== null) {
            $user->forceFill(['locale' => $browser])->saveQuietly();
        }

        app()->setLocale($locale);

        return $next($request);
    }

    /**
     * The first browser-preferred language we actually support, or null.
     *
     * @param  list<string>  $supported
     */
    private function fromBrowser(Request $request, array $supported): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $short = mb_strtolower(mb_substr((string) $language, 0, 2));

            if (in_array($short, $supported, true)) {
                return $short;
            }
        }

        return null;
    }
}
