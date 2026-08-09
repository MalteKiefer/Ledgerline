<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses requests from blocked source IPs (single address or CIDR) and from
 * blocked user accounts. Runs early on every web + API request. The IP block
 * list is cached briefly to keep this off the hot path; the cache is flushed
 * when the admin adds/removes a block.
 */
class BlockGuard
{
    public const CACHE_KEY = 'security.blocked_ips';

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->ip();
        if ($ip !== '' && $this->ipBlocked($ip)) {
            abort(403, 'Your address has been blocked.');
        }

        $user = $request->user();
        if ($user instanceof User && $user->isBlocked()) {
            // Deny + tear down any auth so a blocked account can't act.
            abort(403, 'This account has been blocked.');
        }

        return $next($request);
    }

    private function ipBlocked(string $ip): bool
    {
        /** @var list<string> $cidrs */
        $cidrs = Cache::remember(self::CACHE_KEY, 60, static fn (): array => BlockedIp::query()->pluck('cidr')->all());
        foreach ($cidrs as $cidr) {
            if (BlockedIp::ipMatchesCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }
}
