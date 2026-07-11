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
];
