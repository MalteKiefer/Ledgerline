<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared response tail for controllers that back a reload-free Alpine client:
 * an XHR gets a JSON acknowledgement, a plain web request gets a redirect back
 * (optionally to a named target) with an optional translated flash status.
 *
 * Matches the existing idiom exactly:
 *   $request->expectsJson() ? response()->json(['ok' => true, ...]) : back()
 */
trait RespondsFlexibly
{
    /**
     * @param  array<string, mixed>  $json  Extra payload merged alongside ['ok' => true] for XHR callers.
     * @param  string|null  $flashKey  Translation key flashed as 'status' on the redirect branch (null = no flash).
     * @param  array<string, mixed>  $flashParams  Replacement params for the translated flash string.
     * @param  string|null  $back  Explicit redirect target; when null, redirects back().
     */
    protected function flexible(Request $request, array $json = [], ?string $flashKey = null, array $flashParams = [], ?string $back = null): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => true] + $json);
        }

        $redirect = $back !== null ? redirect()->to($back) : redirect()->back();

        return $flashKey !== null ? $redirect->with('status', __($flashKey, $flashParams)) : $redirect;
    }
}
