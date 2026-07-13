<?php

declare(strict_types=1);

return [
    /*
     * Bearer/query token guarding the Prometheus /metrics endpoint. When empty
     * the endpoint is disabled (returns 404). Set OPS_METRICS_TOKEN to enable.
     */
    'metrics_token' => env('OPS_METRICS_TOKEN', ''),

    /*
     * Notify the configured channels when new unresolved errors appear. The
     * hourly ops:alert-errors command reports at most once per this window.
     */
    'error_alerts' => (bool) env('OPS_ERROR_ALERTS', true),

    /*
     * Retention (days) for the append-only security audit log. Entries older
     * than this are pruned daily. 0 keeps them forever.
     */
    'audit_retention_days' => (int) env('OPS_AUDIT_RETENTION_DAYS', 365),

    /*
     * Alert when the most recent successful backup is older than this many hours
     * (backup staleness). 0 disables the staleness check.
     */
    'backup_stale_hours' => (int) env('OPS_BACKUP_STALE_HOURS', 48),
];
