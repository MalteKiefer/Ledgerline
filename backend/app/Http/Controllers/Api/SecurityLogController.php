<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only API mirror of Settings\SecurityLogController.
 *
 * Admin-gated (manage-global-settings + abilities:device). The audit_logs table
 * is metadata-only — no tokens, ciphertext, or secrets — so it is safe to
 * expose to an admin device token. Throttled conservatively so a single token
 * cannot bulk-dump the table in a tight loop.
 */
class SecurityLogController extends Controller
{
    /** Maximum entries returned by the paginated index. */
    private const MAX_PER_PAGE = 100;

    /** Hard cap on the streaming export to prevent table-dumps. */
    private const EXPORT_CAP = 10_000;

    /**
     * Paginated, filterable audit-log listing.
     *
     * Filters:
     *   ?action=   – exact match, OR trailing '.*' for prefix (device.* → device.%)
     *   ?user=     – integer user_id
     *   ?since=    – ISO-8601 datetime or relative shorthand (-24h / -7d)
     *   ?page=     – page number (default 1)
     *   ?per_page= – entries per page, clamped to 1–100 (default 50)
     *
     * Returns:
     *   { data: [SecurityLogEntry…], meta: { total, per_page, current_page, last_page } }
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max(1, $request->integer('per_page', 50)), self::MAX_PER_PAGE);

        $paginator = $this->filtered($request)
            ->with('actor:id,name')
            ->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn (AuditLog $r): array => $this->present($r))->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Streaming export of the filtered log, capped at 10 000 entries.
     *
     * Query params match index; additionally:
     *   ?format= csv (default) | json
     *   ?limit=  1–10000 (default 10000)
     *
     * CSV cells derived from untrusted input are formula-injection-neutralised
     * with the same csvSafe() logic used in the web controller.
     * The NDJSON stream emits one JSON object per line.
     */
    public function export(Request $request): StreamedResponse
    {
        $format = $request->string('format')->value();
        if ($format !== 'json') {
            $format = 'csv';
        }

        $limit = min(max(1, $request->integer('limit', self::EXPORT_CAP)), self::EXPORT_CAP);
        $rows = $this->filtered($request)->with('actor:id,name')->limit($limit)->get();
        $stamp = Carbon::now()->format('Ymd-His');

        if ($format === 'json') {
            return response()->streamDownload(function () use ($rows): void {
                foreach ($rows as $r) {
                    /** @var AuditLog $r */
                    echo json_encode($this->present($r), JSON_UNESCAPED_SLASHES).PHP_EOL;
                }
            }, "security-log-{$stamp}.jsonl", ['Content-Type' => 'application/x-ndjson']);
        }

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['at', 'user_id', 'actor', 'action', 'ip', 'user_agent', 'meta']);
            foreach ($rows as $r) {
                /** @var AuditLog $r */
                fputcsv($out, [
                    $r->created_at?->toIso8601String(),
                    $r->user_id,
                    self::csvSafe($r->actor?->name),
                    self::csvSafe($r->action),
                    self::csvSafe($r->ip),
                    self::csvSafe($r->user_agent),
                    self::csvSafe(json_encode($r->meta, JSON_UNESCAPED_SLASHES) ?: ''),
                ]);
            }
            fclose($out);
        }, "security-log-{$stamp}.csv", ['Content-Type' => 'text/csv']);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Build the ordered, filtered query — shared by index and export.
     * Mirrors Settings\SecurityLogController::filtered() exactly.
     *
     * @return Builder<AuditLog>
     */
    private function filtered(Request $request): Builder
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        $action = $request->string('action')->value();
        if ($action !== '') {
            str_ends_with($action, '.*')
                ? $query->where('action', 'like', str_replace('.*', '.', $action).'%')
                : $query->where('action', $action);
        }

        if (is_numeric($request->input('user'))) {
            $query->where('user_id', $request->integer('user'));
        }

        $since = $request->string('since')->value();
        if ($since !== '') {
            try {
                $query->where('created_at', '>=', Carbon::parse($since));
            } catch (\Throwable) {
                // Ignore an unparseable / unsupported shorthand — return all rows.
            }
        }

        return $query;
    }

    /**
     * Serialise one AuditLog row into the API shape.
     *
     * @return array{at: string|null, user_id: int|null, actor: string|null, action: string, ip: string|null, user_agent: string|null, meta: array<string, mixed>|null}
     */
    private function present(AuditLog $r): array
    {
        return [
            'at' => $r->created_at?->toIso8601String(),
            'user_id' => $r->user_id,
            'actor' => $r->actor?->name,
            'action' => $r->action,
            'ip' => $r->ip,
            'user_agent' => $r->user_agent,
            'meta' => $r->meta,
        ];
    }

    /**
     * Neutralise leading formula characters (=, +, -, @, TAB, CR) by prefixing
     * with an apostrophe so spreadsheet applications treat the cell as plain text.
     *
     * Mirrors Settings\SecurityLogController::csvSafe() verbatim.
     */
    private static function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
