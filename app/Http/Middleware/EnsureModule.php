<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind a toggleable application module. The key is either given
 * explicitly (`module:finance`) or read from the route's `{module}` parameter
 * (the generic /store/{module} endpoint). Only KNOWN toggleable modules are
 * enforced — an unlisted store key (e.g. `sharing`) passes through untouched.
 * Admins always pass (they get every module).
 */
final class EnsureModule
{
    /**
     * @param  Closure(Request): Response  $next
     */
    /**
     * Legacy sealed-store keys that differ from their module toggle key. The generic
     * /store/{module} endpoint still accepts the old `invoices` key (dual-read
     * migration), which maps to the `finance` module toggle — without this alias the
     * gate would silently skip it and a disabled-finance account could still read/write
     * the legacy invoices store.
     *
     * @var array<string, string>
     */
    private const ALIASES = ['invoices' => 'finance'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $raw = $module ?? $request->route('module');
        $key = is_string($raw) ? (self::ALIASES[$raw] ?? $raw) : $raw;
        $known = array_keys((array) config('modules.list', []));
        $user = $request->user();

        if (is_string($key) && in_array($key, $known, true) && $user instanceof User && ! $user->canModule($key)) {
            abort(403, 'This module is not available for your account.');
        }

        return $next($request);
    }
}
