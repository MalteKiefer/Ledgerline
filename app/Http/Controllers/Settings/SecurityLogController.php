<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin security-log viewer: the append-only audit_logs, filterable by action /
 * user / time, with CSV + JSON export. Read-only. Metadata only — the audit rows
 * never contain secrets, so the export is safe to hand to an operator.
 */
class SecurityLogController extends Controller
{
    /** How many entries per page in the table view. */
    private const PER_PAGE = 50;

    public function index(Request $request): View|StreamedResponse
    {
        $query = $this->filtered($request);

        $export = $request->string('export')->value();
        if ($export === 'csv' || $export === 'json') {
            return $this->export($query, $export);
        }

        // Distinct actions for the filter dropdown (cheap — the set is small).
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action')->all();

        return view('settings.security-log.index', [
            'logs' => $query->with('actor')->paginate(self::PER_PAGE)->withQueryString(),
            'actions' => $actions,
            'filters' => [
                'action' => $request->string('action')->value(),
                'user' => $request->string('user')->value(),
                'since' => $request->string('since')->value(),
            ],
        ]);
    }

    /**
     * Build the filtered, newest-first query from the request.
     *
     * @return Builder<AuditLog>
     */
    private function filtered(Request $request): Builder
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        $action = $request->string('action')->value();
        if ($action !== '') {
            // A trailing ".*" matches a prefix group (e.g. device.*).
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
                // ignore an unparseable date
            }
        }

        return $query;
    }

    /**
     * Stream the filtered log as CSV or newline-delimited JSON (capped so an
     * export can't be used to dump the whole table in one go).
     *
     * @param  Builder<AuditLog>  $query
     */
    private function export(Builder $query, string $format): StreamedResponse
    {
        $rows = $query->with('actor')->limit(10000)->get();
        $stamp = Carbon::now()->format('Ymd-His');

        if ($format === 'json') {
            return response()->streamDownload(function () use ($rows): void {
                foreach ($rows as $r) {
                    echo json_encode([
                        'at' => $r->created_at?->toIso8601String(),
                        'user_id' => $r->user_id,
                        'actor' => $r->actor?->name,
                        'action' => $r->action,
                        'ip' => $r->ip,
                        'user_agent' => $r->user_agent,
                        'meta' => $r->meta,
                    ], JSON_UNESCAPED_SLASHES).PHP_EOL;
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
                // Neutralise formula prefixes in every cell derived from untrusted
                // input (actor name, user-agent, and the meta blob which carries a
                // user-chosen device_name) so a spreadsheet can't execute it.
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

    /**
     * Prefix a leading formula character with an apostrophe so a spreadsheet
     * treats the cell as text (CSV/formula-injection defence).
     */
    private static function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
