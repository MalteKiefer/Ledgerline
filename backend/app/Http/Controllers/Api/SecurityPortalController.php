<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\BlockGuard;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\RequestLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin security portal: the verbose request log, IP block-list, per-user block,
 * and a cross-user session/device overview. Admin-gated at the route level
 * (manage-global-settings + abilities:device). Metadata only.
 */
class SecurityPortalController extends Controller
{
    private const EXPORT_CAP = 50000;

    /** Paginated, filterable request log (newest first). */
    public function requestLog(Request $request): JsonResponse
    {
        $rows = $this->requestQuery($request)->with('user:id,name,email')->paginate(
            perPage: min(200, max(10, $request->integer('per_page', 50))),
        );

        return response()->json([
            'data' => $rows->getCollection()->map(fn (RequestLog $r): array => $this->row($r))->all(),
            'meta' => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    /** Streaming CSV/JSON export of the filtered request log (capped). */
    public function requestLogExport(Request $request): StreamedResponse
    {
        $format = $request->string('export')->value() === 'json' ? 'json' : 'csv';
        $rows = $this->requestQuery($request)->with('user:id,name')->limit(self::EXPORT_CAP)->get();
        $name = 'request-log-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'json') {
            return response()->streamDownload(function () use ($rows): void {
                echo $rows->map(fn (RequestLog $r): array => $this->row($r))->toJson(JSON_PRETTY_PRINT);
            }, $name, ['Content-Type' => 'application/json']);
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['time', 'ip', 'method', 'path', 'status', 'user', 'user_agent', 'duration_ms']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    self::csvSafe($r->created_at?->toIso8601String()),
                    self::csvSafe($r->ip),
                    self::csvSafe($r->method),
                    self::csvSafe($r->path),
                    (string) $r->status,
                    self::csvSafe($r->user?->name),
                    self::csvSafe($r->user_agent),
                    (string) ($r->duration_ms ?? ''),
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** @return Builder<RequestLog> */
    private function requestQuery(Request $request): Builder
    {
        return RequestLog::query()
            ->when($request->filled('ip'), fn ($q) => $q->where('ip', $request->string('ip')->value()))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->when($request->filled('method'), fn ($q) => $q->where('method', strtoupper($request->string('method')->value())))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->integer('status')))
            ->when($request->filled('path'), fn ($q) => $q->where('path', 'like', '%'.$request->string('path')->value().'%'))
            ->when($request->filled('since'), function ($q) use ($request) {
                // Parse defensively — a bad `since` must not 500 the query.
                try {
                    $q->where('created_at', '>=', Carbon::parse($request->string('since')->value()));
                } catch (\Throwable) {
                    // ignore an unparseable filter (return all rows)
                }
            })
            ->orderByDesc('id');
    }

    /** @return array<string, mixed> */
    private function row(RequestLog $r): array
    {
        return [
            'id' => $r->id,
            'time' => $r->created_at?->toIso8601String(),
            'ip' => $r->ip,
            'method' => $r->method,
            'path' => $r->path,
            'status' => $r->status,
            'user' => $r->user !== null ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
            'user_agent' => $r->user_agent,
            'duration_ms' => $r->duration_ms,
        ];
    }

    /* ---- IP block list ---- */

    public function blocks(): JsonResponse
    {
        $rows = BlockedIp::query()->orderByDesc('id')->get();

        return response()->json([
            'blocks' => $rows->map(fn (BlockedIp $b): array => [
                'id' => $b->id,
                'cidr' => $b->cidr,
                'reason' => $b->reason,
                'created_at' => $b->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function blockIp(Request $request): JsonResponse
    {
        $request->validate([
            'cidr' => ['required', 'string', 'max:64', 'unique:blocked_ips,cidr'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $cidr = $request->string('cidr')->value();
        // Reject a typo that can't parse as an IP/CIDR (would be a dead rule).
        $probe = str_contains($cidr, '/') ? explode('/', $cidr, 2)[0] : $cidr;
        if (@inet_pton($probe) === false) {
            throw ValidationException::withMessages(['cidr' => ['Not a valid IP address or CIDR range.']]);
        }

        $block = BlockedIp::create([
            'cidr' => $cidr,
            'reason' => $request->filled('reason') ? $request->string('reason')->value() : null,
            'created_by' => $this->requireUser($request)->id,
        ]);
        Cache::forget(BlockGuard::CACHE_KEY);
        AuditLog::record('security.ip_blocked', null, ['cidr' => $block->cidr]);

        return response()->json(['id' => $block->id], 201);
    }

    public function unblockIp(BlockedIp $blockedIp): JsonResponse
    {
        $cidr = $blockedIp->cidr;
        $blockedIp->delete();
        Cache::forget(BlockGuard::CACHE_KEY);
        AuditLog::record('security.ip_unblocked', null, ['cidr' => $cidr]);

        return response()->json(['ok' => true]);
    }

    /* ---- User block ---- */

    public function blockUser(Request $request, User $user): JsonResponse
    {
        // Never let an admin block themselves out of the portal.
        abort_if($user->id === $this->requireUser($request)->id, 422, 'You cannot block yourself.');
        $at = now();
        // Also revoke the WebDAV/CardDAV/CalDAV credential — /dav authenticates via
        // HTTP Basic outside the guard stack, so blocked_at alone would not stop it
        // (WebDavAuth now checks isBlocked() too; this is defence-in-depth).
        $user->forceFill(['blocked_at' => $at, 'webdav_password' => null])->save();
        // Revoke all device tokens. Web sessions are enforced driver-agnostically
        // by the appended BlockGuard on every web request (the DB sessions delete
        // below only helps the database session driver, not Redis).
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        AuditLog::record('security.user_blocked', $user, []);

        return response()->json(['ok' => true, 'blocked_at' => $at->toIso8601String()]);
    }

    public function unblockUser(User $user): JsonResponse
    {
        $user->forceFill(['blocked_at' => null])->save();
        AuditLog::record('security.user_unblocked', $user, []);

        return response()->json(['ok' => true]);
    }

    /* ---- Cross-user session/device overview ---- */

    public function sessions(): JsonResponse
    {
        // Active persisted web sessions across all users.
        $webSessions = DB::table('sessions')->whereNotNull('user_id')->orderByDesc('last_activity')->limit(500)->get()
            ->map(fn (object $s): array => [
                'kind' => 'web',
                'user_id' => $s->user_id,
                'ip' => $s->ip_address ?? null,
                'user_agent' => $s->user_agent ?? null,
                'last_activity' => is_numeric($s->last_activity) ? date('c', (int) $s->last_activity) : null,
            ])->all();

        // Device tokens (native/CLI) across all users.
        $tokens = DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->orderByDesc('last_used_at')->limit(500)->get()
            ->map(fn (object $t): array => [
                'kind' => 'device',
                'user_id' => $t->tokenable_id,
                'name' => $t->name,
                'ip' => $t->ip ?? null,
                'last_used_at' => $t->last_used_at,
            ])->all();

        return response()->json(['web' => $webSessions, 'devices' => $tokens]);
    }

    /** Neutralize spreadsheet formula injection in an exported CSV cell. */
    private static function csvSafe(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
