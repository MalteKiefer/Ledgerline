<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Ops\SystemStatus;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Prometheus text-exposition endpoint for external scraping (Grafana/Prometheus).
 *
 * Disabled unless METRICS_TOKEN is set; when set, a caller must present it as a
 * Bearer token or ?token=. Exposes only aggregate operational gauges — no
 * user content, in keeping with the zero-knowledge posture.
 */
class MetricsController extends Controller
{
    public function index(Request $request, SystemStatus $status): Response
    {
        $token = (string) config('ops.metrics_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, $this->presentedToken($request)), 403);

        $s = $status->snapshot();

        $lines = [];
        $gauge = function (string $name, string $help, int|float $value, array $labels = []) use (&$lines): void {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} gauge";
            $label = '';
            if ($labels !== []) {
                $parts = [];
                foreach ($labels as $k => $v) {
                    $parts[] = $k.'="'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v).'"';
                }
                $label = '{'.implode(',', $parts).'}';
            }
            $lines[] = "{$name}{$label} {$value}";
        };

        $ts = fn (?string $iso): int => $iso ? CarbonImmutable::parse($iso)->getTimestamp() : 0;

        $gauge('ledgerline_up', 'Application serving.', 1);
        $lines[] = '# HELP ledgerline_build_info Build version.';
        $lines[] = '# TYPE ledgerline_build_info gauge';
        $lines[] = 'ledgerline_build_info{version="'.$s['version'].'"} 1';

        foreach ($s['storage'] as $module => $bytes) {
            if ($module === 'total') {
                continue;
            }
            $gauge('ledgerline_storage_bytes', 'Storage bytes in use per module.', $bytes, ['module' => $module]);
        }

        $gauge('ledgerline_queue_pending_jobs', 'Pending queued jobs.', $s['queue']['pending']);
        $gauge('ledgerline_queue_failed_jobs', 'Failed queued jobs.', $s['queue']['failed']);
        $gauge('ledgerline_errors_unresolved', 'Unresolved recorded errors.', $s['errors']['unresolved']);
        $gauge('ledgerline_errors_total', 'Total recorded error occurrences.', $s['errors']['total']);
        $gauge('ledgerline_backup_last_success_timestamp_seconds', 'Last successful backup (unix time).', $ts($s['backup']['lastSuccessAt']));
        $lastSuccessTs = $ts($s['backup']['lastSuccessAt']);
        $gauge('ledgerline_backup_age_seconds', 'Seconds since the last successful backup (0 if none).', $lastSuccessTs > 0 ? max(0, CarbonImmutable::now()->getTimestamp() - $lastSuccessTs) : 0);
        $gauge('ledgerline_backup_verify_status', 'Latest backup verification (1 ok, 0 failed/none).', ($s['backup']['lastVerifyStatus'] ?? null) === 'ok' ? 1 : 0);
        $gauge('ledgerline_scheduler_last_run_timestamp_seconds', 'Last scheduler run (unix time).', $ts($s['scheduler']['lastRunAt']));
        $gauge('ledgerline_disk_free_bytes', 'Free bytes on the storage volume.', $s['disk']['free']);
        $gauge('ledgerline_disk_total_bytes', 'Total bytes on the storage volume.', $s['disk']['total']);

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function presentedToken(Request $request): string
    {
        $bearer = $request->bearerToken();

        return $bearer !== null && $bearer !== '' ? $bearer : (string) $request->query('token', '');
    }
}
